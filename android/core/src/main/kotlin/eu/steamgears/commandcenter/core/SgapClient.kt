package eu.steamgears.commandcenter.core

import eu.steamgears.commandcenter.core.model.Draft
import eu.steamgears.commandcenter.core.model.DraftStatusChange
import eu.steamgears.commandcenter.core.model.NewQueueItem
import eu.steamgears.commandcenter.core.model.QueueStatus
import eu.steamgears.commandcenter.core.model.QueueStatusChange
import eu.steamgears.commandcenter.core.model.QueuedItem
import eu.steamgears.commandcenter.core.model.Snapshot
import eu.steamgears.commandcenter.core.model.Stats
import java.io.IOException
import kotlinx.coroutines.CancellationException
import kotlinx.serialization.Serializable
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import okhttp3.Credentials

/**
 * Tynd klient mod `sgap/v1` med application password som Basic auth.
 *
 * Al logik bliver i WordPress-pluginnet; klienten sender det samme som
 * dashbordet ville sende og læser det samme svar. Derfor er den også kedelig
 * med vilje: ingen cache, ingen retry-loop, ingen egen tilstand. Er svaret
 * uklart, siger den det, i stedet for at gætte.
 *
 * @param config hvor og som hvem. Se [SiteConfig.parse] for normalisering.
 * @param transport kan erstattes i tests; standard er OkHttp.
 */
class SgapClient(
    private val config: SiteConfig,
    private val transport: HttpTransport = OkHttpTransport(),
) : SgapApi {

    private val json = Json {
        ignoreUnknownKeys = true   // pluginnet må gerne få nye felter uden at appen skal opdateres
        coerceInputValues = true   // null i et felt med default bliver default, ikke en fejl
        isLenient = false          // men vi accepterer ikke JSON der ikke er JSON
    }

    override suspend fun snapshot(days: Int, limit: Int): ApiResult<Snapshot> =
        get(config.endpoint("snapshot") + "?days=${days.coerceIn(1, 365)}&limit=${limit.coerceIn(1, 500)}") {
            json.decodeFromString(Snapshot.serializer(), it)
        }

    override suspend fun drafts(status: DraftStatus, limit: Int): ApiResult<List<Draft>> =
        get(config.endpoint("drafts") + "?status=${status.wire}&limit=${limit.coerceIn(1, 100)}") {
            json.decodeFromString(ListSerializer(Draft.serializer()), it)
        }

    override suspend fun setDraftStatus(id: Int, status: DraftStatus): ApiResult<DraftStatusChange> {
        require(id > 0) { "Kladde-id skal være positivt (fik $id)." }
        return post(config.endpoint("drafts", id.toString(), "status"), """{"status":"${status.wire}"}""") {
            json.decodeFromString(DraftStatusChange.serializer(), it)
        }
    }

    override suspend fun enqueue(item: NewQueueItem): ApiResult<QueuedItem> {
        // Pluginnet afviser det samme med en 400; her fanges det før opslaget forlader telefonen.
        require(item.channel.isNotBlank()) { "Kanal mangler, fx pinterest eller facebook." }
        require(item.title.isNotBlank()) { "Titlen mangler — den er det opslaget hedder i køen." }
        require(item.destinationUrl.isNotBlank()) { "Destinationslinket mangler; pluginnet opfinder ikke et selv." }
        val body = buildJsonObject {
            item.toMap().forEach { (key, value) -> put(key, JsonPrimitive(value)) }
        }.toString()
        return post(config.endpoint("queue"), body) {
            json.decodeFromString(QueuedItem.serializer(), it)
        }
    }

    override suspend fun setQueueStatus(
        id: Int,
        status: QueueStatus,
        error: String,
    ): ApiResult<QueueStatusChange> {
        require(id > 0) { "Kø-id skal være positivt (fik $id)." }
        val body = buildJsonObject {
            put("status", JsonPrimitive(status.wire))
            if (error.isNotBlank()) put("error", JsonPrimitive(error))   // pluginnet gemmer den til næste visning
        }.toString()
        return post(config.endpoint("queue", id.toString(), "status"), body) {
            json.decodeFromString(QueueStatusChange.serializer(), it)
        }
    }

    override suspend fun stats(days: Int): ApiResult<Stats> =
        get(config.endpoint("stats") + "?days=${days.coerceIn(1, 365)}") {
            json.decodeFromString(Stats.serializer(), it)
        }

    // ---- transport og fejloversættelse ----

    private suspend fun <T> get(url: String, parse: (String) -> T): ApiResult<T> =
        call("GET", url, null, parse)

    private suspend fun <T> post(url: String, body: String, parse: (String) -> T): ApiResult<T> =
        call("POST", url, body, parse)

    private suspend fun <T> call(
        method: String,
        url: String,
        body: String?,
        parse: (String) -> T,
    ): ApiResult<T> {
        val request = HttpRequest(
            method = method,
            url = url,
            headers = mapOf(
                "Authorization" to Credentials.basic(config.username, config.authPassword),
                "Accept" to "application/json",
            ),
            body = body,
        )

        val response = try {
            transport.execute(request)
        } catch (e: CancellationException) {
            throw e
        } catch (e: IOException) {
            return ApiResult.Failure.Network(
                "Kunne ikke nå ${config.baseUrl}. Tjek forbindelsen og at sitet svarer.",
                e,
            )
        }

        val failure = failureFor(response)
        if (failure != null) return failure

        return try {
            ApiResult.Ok(parse(response.body))
        } catch (e: SerializationException) {
            ApiResult.Failure.Parse(
                "Svaret fra WordPress kunne ikke læses ($method ${request.url}). " +
                    "Er steamgears-autopilot opdateret til en version med sgap/v1?",
                e,
            )
        } catch (e: IllegalArgumentException) {
            ApiResult.Failure.Parse(
                "Svaret fra WordPress havde en uventet form ($method ${request.url}).",
                e,
            )
        }
    }

    /** `null` når svaret er et successvar der kan parses, ellers den fejl appen skal vise. */
    private fun failureFor(response: HttpResponse): ApiResult.Failure? {
        if (response.code in 200..299) {
            if (response.body.isBlank()) {
                return ApiResult.Failure.Parse("WordPress svarede tomt (HTTP ${response.code}).")
            }
            return null
        }

        val error = parseWordPressError(response.body)
        return when (response.code) {
            401 -> ApiResult.Failure.Unauthorized(
                "WordPress afviste login (401). Brug brugernavnet og et application password — " +
                    "ikke den normale adgangskode. Det laves under Brugere → Profil → Application Passwords.",
            )

            403 -> ApiResult.Failure.Unauthorized(
                "WordPress sagde nej (403): brugeren \"${config.username}\" må ikke styre autopiloten. " +
                    "Det kræver administratorrettigheden manage_options.${error?.let { " WordPress siger: ${it.message}" } ?: ""}",
            )

            404 -> ApiResult.Failure.Http(
                404,
                error?.code ?: "rest_no_route",
                "Endepunktet findes ikke på ${config.baseUrl}. Er steamgears-autopilot aktiveret, " +
                    "og er permalinks sat til andet end \"Plain\"?",
            )

            else -> ApiResult.Failure.Http(
                response.code,
                error?.code ?: "http_${response.code}",
                error?.message?.takeIf { it.isNotBlank() }
                    ?: "WordPress svarede HTTP ${response.code}: ${response.body.snippet()}",
            )
        }
    }

    private fun parseWordPressError(body: String): WordPressError? = try {
        json.decodeFromString(WordPressError.serializer(), body)
    } catch (e: SerializationException) {
        null
    } catch (e: IllegalArgumentException) {
        null
    }

    /** WordPress' fejlformat: `{"code":"sgap_invalid","message":"...","data":{"status":400}}`. */
    @Serializable
    private data class WordPressError(
        val code: String = "",
        val message: String = "",
    )

    private fun String.snippet(): String {
        val oneLine = replace(Regex("\\s+"), " ").trim()
        return if (oneLine.length <= 160) oneLine else oneLine.take(157) + "…"
    }
}
