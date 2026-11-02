package eu.steamgears.commandcenter.core

import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertIs
import kotlin.test.assertTrue
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer

class ApiErrorTest {

    private val server = MockWebServer().apply { start() }

    @AfterTest
    fun stop() {
        server.shutdown()
    }

    private fun client(): SgapClient = SgapClient(
        SiteConfig.parse("http://127.0.0.1:${server.port}", "owner", "abcdefgh"),
        OkHttpTransport(),
    )

    private fun failure(body: String = "", code: Int = 200): ApiResult.Failure {
        server.enqueue(MockResponse().setResponseCode(code).setBody(body))
        val result = runBlocking { client().snapshot() }
        return assertIs<ApiResult.Failure>(result)
    }

    @Test
    fun `401 points at application passwords, not the normal password`() {
        val error = assertIs<ApiResult.Failure.Unauthorized>(
            failure("""{"code":"rest_not_logged_in","message":"You are not currently logged in."}""", 401),
        )
        assertTrue(error.message.contains("application password"), error.message)
        assertTrue(error.message.contains("Brugere → Profil"), error.message)
    }

    @Test
    fun `403 says the user lacks the capability and repeats WordPress' own reason`() {
        val error = assertIs<ApiResult.Failure.Unauthorized>(
            failure("""{"code":"rest_forbidden","message":"Sorry, you are not allowed to do that."}""", 403),
        )
        assertTrue(error.message.contains("manage_options"), error.message)
        assertTrue(error.message.contains("Sorry, you are not allowed"), error.message)
    }

    @Test
    fun `404 asks whether the plugin is active instead of blaming the network`() {
        val error = assertIs<ApiResult.Failure.Http>(
            failure("""{"code":"rest_no_route","message":"No route was found."}""", 404),
        )
        assertEquals(404, error.status)
        assertEquals("rest_no_route", error.code)
        assertTrue(error.message.contains("steamgears-autopilot"), error.message)
    }

    @Test
    fun `a plugin validation error keeps its code and message`() {
        val error = assertIs<ApiResult.Failure.Http>(
            failure("""{"code":"sgap_invalid","message":"status must be pending, approved, rejected or used.","data":{"status":400}}""", 400),
        )
        assertEquals(400, error.status)
        assertEquals("sgap_invalid", error.code)
        assertEquals("status must be pending, approved, rejected or used.", error.message)
    }

    @Test
    fun `a WordPress 500 page is reported as HTTP 500 without dumping the whole page`() {
        val html = "<html><body><h1>Fatal error</h1>" + "x".repeat(5000) + "</body></html>"
        val error = assertIs<ApiResult.Failure.Http>(failure(html, 500))
        assertEquals(500, error.status)
        assertEquals("http_500", error.code)
        assertTrue(error.message.contains("HTTP 500"), error.message)
        assertTrue(error.message.length < 400, "beskeden må ikke være hele fejlsiden: ${error.message.length} tegn")
    }

    @Test
    fun `HTML with a 200 is a parse failure, not an empty dashboard`() {
        val error = assertIs<ApiResult.Failure.Parse>(failure("<html>maintenance</html>"))
        assertTrue(error.message.contains("kunne ikke læses"), error.message)
    }

    @Test
    fun `an empty 200 is a parse failure`() {
        val error = assertIs<ApiResult.Failure.Parse>(failure(""))
        assertTrue(error.message.contains("tomt"), error.message)
    }

    @Test
    fun `an unreachable site is a network failure with the address in the message`() {
        val client = client()
        server.shutdown()
        val result = runBlocking { client.snapshot() }
        val error = assertIs<ApiResult.Failure.Network>(result)
        assertTrue(error.message.contains("Kunne ikke nå"), error.message)
    }

    @Test
    fun `one request per call and no hidden retries`() {
        server.enqueue(MockResponse().setResponseCode(500).setBody("nope"))
        runBlocking { client().snapshot() }
        assertEquals(1, server.requestCount)
    }
}
