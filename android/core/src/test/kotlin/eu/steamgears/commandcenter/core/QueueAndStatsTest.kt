package eu.steamgears.commandcenter.core

import eu.steamgears.commandcenter.core.model.NewQueueItem
import eu.steamgears.commandcenter.core.model.QueueStatus
import eu.steamgears.commandcenter.core.model.QueuedItem
import eu.steamgears.commandcenter.core.model.Stats
import kotlin.test.AfterTest
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFailsWith
import kotlin.test.assertFalse
import kotlin.test.assertIs
import kotlin.test.assertTrue
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer

class QueueAndStatsTest {

    private val server = MockWebServer().apply { start() }
    private val json = Json { ignoreUnknownKeys = true }

    @AfterTest
    fun stop() = server.shutdown()

    private fun client(): SgapClient = SgapClient(
        SiteConfig.parse("http://127.0.0.1:${server.port}", "owner", "abcdefgh"),
        OkHttpTransport(),
    )

    private fun body(name: String): String =
        requireNotNull(javaClass.getResource("/$name")).readText()

    @Test
    fun `enqueue posts the three required fields and nothing invented`() = runBlocking {
        server.enqueue(
            MockResponse().setResponseCode(201)
                .setBody("""{"id":42,"tracking_url":"https://steamgears.eu/go/42/","utm_url":"https://steamgears.eu/g/?utm_source=pinterest"}"""),
        )

        val result = client().enqueue(
            NewQueueItem(
                channel = "pinterest",
                title = "Brass goggles",
                destinationUrl = "https://steamgears.eu/product/goggles/",
            ),
        )

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/wp-json/sgap/v1/queue", request.path)
        val sent = json.parseToJsonElement(request.body.readUtf8()).jsonObject
        assertEquals("pinterest", sent["channel"]!!.jsonPrimitive.content)
        assertEquals("Brass goggles", sent["title"]!!.jsonPrimitive.content)
        assertEquals("https://steamgears.eu/product/goggles/", sent["destination_url"]!!.jsonPrimitive.content)
        assertFalse("body" in sent, "tomme felter skal ikke sendes og overskrive WordPress' defaults")
        assertFalse("status" in sent)

        val queued = assertIs<ApiResult.Ok<QueuedItem>>(result).value
        assertEquals(42, queued.id)
        assertEquals("https://steamgears.eu/go/42/", queued.trackingUrl)
        assertTrue(queued.utmUrl.contains("utm_source=pinterest"))
    }

    @Test
    fun `enqueue sends the optional fields when they are filled in`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(201).setBody("""{"id":43,"tracking_url":"","utm_url":""}"""))

        client().enqueue(
            NewQueueItem(
                channel = "facebook",
                title = "Patina is a feature",
                destinationUrl = "https://steamgears.eu/blog/patina/",
                body = "Brass ages well.",
                imageUrl = "https://steamgears.eu/img.jpg",
                angle = "angle_b",
                contentType = "blog",
                format = "post",
                scheduledAt = "2026-11-03 09:00:00",
                status = "draft",
            ),
        )

        val sent = json.parseToJsonElement(server.takeRequest().body.readUtf8()).jsonObject
        assertEquals("Brass ages well.", sent["body"]!!.jsonPrimitive.content)
        assertEquals("angle_b", sent["angle"]!!.jsonPrimitive.content)
        assertEquals("2026-11-03 09:00:00", sent["scheduled_at"]!!.jsonPrimitive.content)
        assertEquals("draft", sent["status"]!!.jsonPrimitive.content)
    }

    @Test
    fun `enqueue refuses a missing destination before any request`() {
        assertFailsWith<IllegalArgumentException> {
            runBlocking {
                client().enqueue(NewQueueItem(channel = "pinterest", title = "T", destinationUrl = "  "))
            }
        }
        assertFailsWith<IllegalArgumentException> {
            runBlocking {
                client().enqueue(NewQueueItem(channel = "  ", title = "T", destinationUrl = "https://x.dk/"))
            }
        }
        assertEquals(0, server.requestCount)
    }

    @Test
    fun `a duplicate is reported with the plugin's own code`() = runBlocking {
        server.enqueue(
            MockResponse().setResponseCode(409)
                .setBody("""{"code":"sgap_duplicate","message":"Already queued.","data":{"status":409,"id":42}}"""),
        )
        val error = assertIs<ApiResult.Failure.Http>(
            client().enqueue(NewQueueItem("pinterest", "Brass goggles", "https://steamgears.eu/p/")),
        )
        assertEquals(409, error.status)
        assertEquals("sgap_duplicate", error.code)
        assertEquals("Already queued.", error.message)
    }

    @Test
    fun `marking a queue item failed carries the error text`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"id":11,"status":"failed"}"""))

        val result = client().setQueueStatus(11, QueueStatus.Failed, error = "publer: token expired")

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/wp-json/sgap/v1/queue/11/status", request.path)
        val sent = json.parseToJsonElement(request.body.readUtf8()).jsonObject
        assertEquals("failed", sent["status"]!!.jsonPrimitive.content)
        assertEquals("publer: token expired", sent["error"]!!.jsonPrimitive.content)
        val change = assertIs<ApiResult.Ok<*>>(result).value as eu.steamgears.commandcenter.core.model.QueueStatusChange
        assertEquals(11, change.id)
        assertEquals("failed", change.status)
    }

    @Test
    fun `queue status without an error sends only the status`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody("""{"id":12,"status":"published"}"""))
        client().setQueueStatus(12, QueueStatus.Published)
        val sent = json.parseToJsonElement(server.takeRequest().body.readUtf8()).jsonObject
        assertEquals(setOf("status"), sent.keys)
    }

    @Test
    fun `stats read the window and add up events across channels`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("stats.json")))

        val stats = assertIs<ApiResult.Ok<Stats>>(client().stats(days = 30)).value

        assertEquals("/wp-json/sgap/v1/stats?days=30", server.takeRequest().path)
        assertEquals(30, stats.days)
        assertEquals(310, stats.total("click"))       // "310" i JSON
        assertEquals(7, stats.total("order"))
        assertEquals(3, stats.events.size)
        assertEquals(612.40, stats.events.last().value, 0.001)
        assertEquals(11, stats.queue.first { it.status == "sent" }.count)
        assertEquals(0, stats.total("does_not_exist"))
    }

    @Test
    fun `stats window is clamped like snapshot`() = runBlocking {
        server.enqueue(MockResponse().setResponseCode(200).setBody(body("stats.json")))
        client().stats(days = 0)
        assertEquals("/wp-json/sgap/v1/stats?days=1", server.takeRequest().path)
    }
}
