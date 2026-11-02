package eu.steamgears.commandcenter.core

import java.util.concurrent.TimeUnit
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFailsWith
import kotlin.test.assertIs
import kotlin.test.assertTrue
import kotlinx.coroutines.runBlocking
import okhttp3.Credentials
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer

/**
 * Kører mod en rigtig HTTP-server på localhost (MockWebServer), ikke mod en
 * fake transport — så både URL, metode, headers og body bliver prøvet som
 * WordPress ville se dem. Ingen Android SDK, ingen telefon.
 */
class SgapClientTest {

    private val server = MockWebServer().apply { start() }

    @AfterTest
    fun stop() {
        server.shutdown()
    }

    private fun config(path: String = ""): SiteConfig =
        SiteConfig.parse("http://127.0.0.1:${server.port}$path", "owner", "abcd efgh ijkl mnop")

    private fun client(path: String = ""): SgapClient =
        SgapClient(config(path), OkHttpTransport())

    private fun body(name: String): String =
        requireNotNull(javaClass.getResource("/$name")).readText()

    @Test
    fun `snapshot is one GET with the window and admin credentials`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("snapshot.json")))

        val result = client().snapshot()

        val request = server.takeRequest(5, TimeUnit.SECONDS)!!
        assertEquals("GET", request.method)
        assertEquals("/wp-json/sgap/v1/snapshot?days=30&limit=100", request.path)
        assertEquals(
            Credentials.basic("owner", "abcdefghijklmnop"),
            request.getHeader("Authorization"),
            "application password skal sendes uden mellemrum",
        )
        assertEquals("application/json", request.getHeader("Accept"))

        val snapshot = assertIs<ApiResult.Ok<*>>(result).value as eu.steamgears.commandcenter.core.model.Snapshot
        assertEquals("7.17.0", snapshot.version)
        assertEquals(2, snapshot.queue.size)
    }

    @Test
    fun `snapshot window is clamped to what the plugin accepts`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("snapshot.json")))
        client().snapshot(days = 9_999, limit = 0)
        assertEquals("/wp-json/sgap/v1/snapshot?days=365&limit=1", server.takeRequest().path)
    }

    @Test
    fun `a site in a subfolder keeps the prefix in the URL`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("snapshot.json")))
        client("/shop").snapshot()
        assertEquals("/shop/wp-json/sgap/v1/snapshot?days=30&limit=100", server.takeRequest().path)
    }

    @Test
    fun `drafts are filtered by status on the server`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("drafts.json")))

        val result = client().drafts(status = DraftStatus.Approved, limit = 50)

        assertEquals("/wp-json/sgap/v1/drafts?status=approved&limit=50", server.takeRequest().path)
        val drafts = assertIs<ApiResult.Ok<*>>(result).value as List<*>
        assertEquals(1, drafts.size)
    }

    @Test
    fun `a huge limit is cut to the plugin's ceiling of 100`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("[]"))
        client().drafts(limit = 5_000)
        assertEquals("/wp-json/sgap/v1/drafts?status=approved&limit=100", server.takeRequest().path)
    }

    @Test
    fun `approving a draft is a POST with only the status`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"id":31,"status":"approved"}"""))

        val result = client().setDraftStatus(31, DraftStatus.Approved)

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/wp-json/sgap/v1/drafts/31/status", request.path)
        assertEquals("""{"status":"approved"}""", request.body.readUtf8())
        assertTrue(request.getHeader("Content-Type")!!.startsWith("application/json"))
        assertEquals(31, (assertIs<ApiResult.Ok<*>>(result).value as eu.steamgears.commandcenter.core.model.DraftStatusChange).id)
    }

    @Test
    fun `rejecting sends the wire value the plugin validates`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"id":7,"status":"rejected"}"""))
        client().setDraftStatus(7, DraftStatus.Rejected)
        assertEquals("""{"status":"rejected"}""", server.takeRequest().body.readUtf8())
    }

    @Test
    fun `a draft id of zero is refused before any request`() {
        assertFailsWith<IllegalArgumentException> {
            runBlocking { client().setDraftStatus(0, DraftStatus.Approved) }
        }
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `status wire values match the four the plugin accepts`() {
        assertEquals("pending", DraftStatus.Pending.wire)
        assertEquals("approved", DraftStatus.Approved.wire)
        assertEquals("rejected", DraftStatus.Rejected.wire)
        assertEquals("used", DraftStatus.Used.wire)
        assertEquals(DraftStatus.Used, DraftStatus.fromWire("used"))
        assertEquals(null, DraftStatus.fromWire("bogus"))
    }
}
