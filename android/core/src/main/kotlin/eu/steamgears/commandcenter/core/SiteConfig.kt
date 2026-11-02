package eu.steamgears.commandcenter.core

import java.net.URI
import java.net.URISyntaxException

/**
 * Hvor appen taler med WordPress, og hvem den udgiver sig for at være.
 *
 * Adressen er tilgivende med vilje: ejeren klistrer ind hvad browseren viste.
 * `steamgears.eu`, `https://steamgears.eu/`, `https://steamgears.eu/wp-json/`
 * og `https://steamgears.eu/wp-json/sgap/v1` giver alle samme REST-base, og en
 * installation i en undermappe (`https://site.dk/shop`) beholder sin sti.
 *
 * Application passwords vises i WordPress i grupper af fire ord
 * (`abcd efgh ijkl ...`), så mellemrum fjernes før de sendes. Det er ikke
 * kosmetik: med mellemrummene med i headeren svarer WordPress 401.
 */
data class SiteConfig(
    val baseUrl: String,
    val username: String,
    val appPassword: String,
) {
    /** fx `https://steamgears.eu/wp-json/sgap/v1` — uden afsluttende skråstreg. */
    val restBase: String get() = "$baseUrl/wp-json/$API_NAMESPACE"

    /** Fuld URL til et endepunkt, fx `endpoint("drafts", "31", "status")`. */
    fun endpoint(vararg path: String): String =
        restBase + path.joinToString(separator = "") { "/" + it.trim('/') }

    internal val authPassword: String get() = appPassword.replace(WHITESPACE, "")

    companion object {
        const val API_NAMESPACE = "sgap/v1"

        private val WHITESPACE = Regex("\\s+")
        private val SCHEME = Regex("^[a-zA-Z][a-zA-Z0-9+.\\-]*://")
        private val LOCAL_HOSTS = setOf("localhost", "127.0.0.1", "10.0.2.2", "[::1]")

        /**
         * @throws IllegalArgumentException med en dansk besked ejeren kan handle på.
         */
        fun parse(rawBaseUrl: String, username: String, appPassword: String): SiteConfig {
            val user = username.trim()
            require(user.isNotEmpty()) {
                "Brugernavnet mangler. Det er WordPress-login, ikke visningsnavnet."
            }
            require(appPassword.isNotBlank()) {
                "Application password mangler. Det laves i WordPress under Brugere → Profil → Application Passwords."
            }
            return SiteConfig(normalizeBaseUrl(rawBaseUrl), user, appPassword.trim())
        }

        internal fun normalizeBaseUrl(raw: String): String {
            var value = raw.trim()
            require(value.isNotEmpty()) { "Site-adressen mangler, fx https://steamgears.eu." }
            if (!SCHEME.containsMatchIn(value)) value = "https://$value"

            // "https://" alene giver en URISyntaxException længere nede; her bliver beskeden forståelig.
            val authority = value.substringAfter("://").substringBefore('/')
            require(authority.isNotBlank()) {
                "Site-adressen mangler et domænenavn: \"$raw\". Skriv den som https://ditdomæne.dk."
            }

            val uri = try {
                URI(value)
            } catch (e: URISyntaxException) {
                throw IllegalArgumentException("Site-adressen kan ikke læses: \"$raw\".", e)
            }
            val scheme = uri.scheme?.lowercase()
                ?: throw IllegalArgumentException("Site-adressen mangler http:// eller https://: \"$raw\".")
            require(scheme == "http" || scheme == "https") {
                "Kun http og https kan bruges (fik \"$scheme\")."
            }
            val host = uri.host?.lowercase()
                ?: throw IllegalArgumentException("Site-adressen mangler et domænenavn: \"$raw\".")
            require(uri.query == null && uri.fragment == null) {
                "Site-adressen må ikke have ?spørgsmål eller #anker — kun selve adressen."
            }
            require(scheme == "https" || host in LOCAL_HOSTS) {
                "Brug https:// for $host — over http sendes application password i klartekst."
            }

            var path = (uri.path ?: "").trimEnd('/')
            for (suffix in listOf("/wp-json/$API_NAMESPACE", "/wp-json")) {
                if (path.endsWith(suffix)) path = path.removeSuffix(suffix).trimEnd('/')
            }
            val port = if (uri.port == -1) "" else ":${uri.port}"
            return "$scheme://$host$port$path"
        }
    }
}
