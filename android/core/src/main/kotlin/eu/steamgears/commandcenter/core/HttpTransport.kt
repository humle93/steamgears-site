package eu.steamgears.commandcenter.core

import java.io.IOException
import java.util.concurrent.TimeUnit
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody

/** Ét HTTP-kald, som transporten skal udføre. Uafhængigt af OkHttp, så tests kan erstatte det. */
data class HttpRequest(
    val method: String,
    val url: String,
    val headers: Map<String, String> = emptyMap(),
    val body: String? = null,
)

data class HttpResponse(val code: Int, val body: String)

/** Grænsefladen klienten bruger til at tale med nettet. Kan erstattes i tests. */
interface HttpTransport {
    /** @throws IOException når serveren ikke kan nås. */
    suspend fun execute(request: HttpRequest): HttpResponse
}

/** Standardtransporten: OkHttp med tidsgrænser der passer til et site, ikke et API i samme rack. */
class OkHttpTransport(
    private val client: OkHttpClient = defaultClient(),
) : HttpTransport {

    override suspend fun execute(request: HttpRequest): HttpResponse = withContext(Dispatchers.IO) {
        val builder = Request.Builder().url(request.url)
        request.headers.forEach { (name, value) -> builder.header(name, value) }
        val body = request.body?.toRequestBody(JSON)
        builder.method(request.method, body)
        client.newCall(builder.build()).execute().use { response ->
            HttpResponse(response.code, response.body?.string().orEmpty())
        }
    }

    companion object {
        private val JSON = "application/json; charset=utf-8".toMediaType()

        fun defaultClient(): OkHttpClient = OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .callTimeout(30, TimeUnit.SECONDS)
            .retryOnConnectionFailure(true)
            .build()
    }
}
