package eu.steamgears.commandcenter.core

import eu.steamgears.commandcenter.core.model.Draft
import eu.steamgears.commandcenter.core.model.Snapshot
import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFalse
import kotlin.test.assertNotNull
import kotlin.test.assertNull
import kotlin.test.assertTrue
import kotlinx.serialization.builtins.ListSerializer
import kotlinx.serialization.json.Json

/**
 * Fixtures er ikke håndskrevne: `android/core/src/test/fixtures/generate-fixtures.php`
 * kalder `SGAP_REST::snapshot()` i det rigtige plugin og skriver svaret her.
 * Derfor tester de hvad WordPress faktisk sender — inklusive at MySQL giver
 * `"id": "12"` og `"published": "42"` som strenge.
 */
class SnapshotParsingTest {

    private val json = Json { ignoreUnknownKeys = true; coerceInputValues = true }

    private fun fixture(name: String): String =
        requireNotNull(javaClass.getResource("/$name")) { "fixture $name mangler" }.readText()

    private fun snapshot(): Snapshot = json.decodeFromString(Snapshot.serializer(), fixture("snapshot.json"))

    @Test
    fun `snapshot from the real plugin parses`() {
        val snapshot = snapshot()
        assertEquals("7.17.0", snapshot.version)
        assertEquals("Steamgears", snapshot.site.name)
        assertEquals("Europe/Copenhagen", snapshot.site.timezone)
        assertEquals(30, snapshot.window.days)
        assertTrue(snapshot.missingTables.isEmpty())
        assertFalse(snapshot.isBroken)
    }

    @Test
    fun `status answers whether anything is being sent`() {
        val status = snapshot().status
        assertTrue(status.autopilot)
        assertFalse(status.fullAuto)
        assertEquals(5, status.canSendNow)
        assertNull(status.sendingPausedBecause)
        assertEquals(8, status.limits.dailyCap)
        assertEquals("23:00-7:00", status.limits.quietHours)
        assertEquals("Sender — 5 klar nu", snapshot().headline)
        assertTrue(snapshot().isSending)
    }

    @Test
    fun `a pause reason wins over the quota`() {
        val paused = fixture("snapshot.json")
            .replace("\"sending_paused_because\": null", "\"sending_paused_because\": \"quiet_hours\"")
        val snapshot = json.decodeFromString(Snapshot.serializer(), paused)
        assertEquals("Sat på pause: quiet_hours", snapshot.headline)
        assertFalse(snapshot.isSending)
    }

    @Test
    fun `an empty quota and a switched off autopilot read differently`() {
        val emptyQuota = fixture("snapshot.json").replace("\"can_send_now\": 5", "\"can_send_now\": 0")
        assertEquals(
            "Dagens kvote er brugt",
            json.decodeFromString(Snapshot.serializer(), emptyQuota).headline,
        )
        val off = fixture("snapshot.json").replace("\"autopilot\": true", "\"autopilot\": false")
        assertEquals(
            "Autopilot er slået fra",
            json.decodeFromString(Snapshot.serializer(), off).headline,
        )
    }

    @Test
    fun `numeric strings from MySQL become numbers`() {
        val results = snapshot().results
        assertEquals(1234.56, results.revenue, 0.001)
        assertEquals(1000.0, results.budget, 0.001)
        assertEquals(1.23, results.roas, 0.001)
        val pinterest = results.byChannel.first()
        assertEquals("pinterest", pinterest.channel)
        assertEquals(42, pinterest.published)   // "42" i JSON
        assertEquals(310, pinterest.clicks)
        assertEquals(7, pinterest.orders)
        assertEquals(612.40, pinterest.revenue, 0.001)
    }

    @Test
    fun `queue rows keep their ids, nulls and tracking links`() {
        val queue = snapshot().queue
        assertEquals(2, queue.size)
        assertEquals(12, queue[0].id)
        assertEquals("sent", queue[0].status)
        assertEquals("https://steamgears.eu/go/12/", queue[0].trackingUrl)
        assertFalse(queue[0].hasFailed)
        assertEquals(11, queue[1].id)
        assertEquals(3, queue[1].attempts)
        assertNull(queue[1].sentAt)
        assertNull(queue[1].publishedAt)
        assertEquals("publer: token expired", queue[1].error)
        assertTrue(queue[1].hasFailed)
    }

    @Test
    fun `drafts carry what they promote and whether a photo exists`() {
        val drafts = snapshot().drafts
        assertEquals(2, drafts.size)
        val instagram = drafts[0]
        assertEquals(31, instagram.id)
        assertEquals("instagram", instagram.channel)
        assertNotNull(instagram.promotes)
        assertEquals("Steampunk Brass Goggles", instagram.promotes.title)
        assertEquals("https://steamgears.eu/product/108/", instagram.promotes.url)
        assertTrue(instagram.hasImage)
        assertTrue(instagram.hasDestination)
        assertEquals("#steampunk #cosplay", instagram.hashtags)
        assertTrue(instagram.isReadyToPublish)

        val facebook = drafts[1]
        assertNull(facebook.promotes)
        assertFalse(facebook.hasDestination)
        assertFalse(facebook.isReadyToPublish)   // stadig kun pending
        assertEquals(0, facebook.postId)         // snapshot udelader post_id
    }

    @Test
    fun `the drafts endpoint returns raw rows with post_id`() {
        val drafts = json.decodeFromString(ListSerializer(Draft.serializer()), fixture("drafts.json"))
        assertEquals(1, drafts.size)
        val draft = drafts[0]
        assertEquals(31, draft.id)
        assertEquals(108, draft.postId)          // "108" i JSON
        assertEquals("approved", draft.status)
        assertEquals("Goggles that actually fit", draft.title)
        assertNull(draft.promotes)               // listens rækker er ikke beriget
    }

    @Test
    fun `unknown sections and new fields do not break the client`() {
        val withExtra = fixture("snapshot.json").replaceFirst(
            "\"version\"",
            "\"future_section\": {\"a\": [1, 2, 3]}, \"version\"",
        )
        val snapshot = json.decodeFromString(Snapshot.serializer(), withExtra)
        assertEquals("7.17.0", snapshot.version)
    }

    @Test
    fun `missing tables are surfaced instead of looking like an empty site`() {
        val broken = fixture("snapshot.json").replace(
            "\"missing_tables\": []",
            "\"missing_tables\": [\"queue\", \"drafts\"]",
        )
        val snapshot = json.decodeFromString(Snapshot.serializer(), broken)
        assertTrue(snapshot.isBroken)
        assertEquals(listOf("queue", "drafts"), snapshot.missingTables)
    }

    @Test
    fun `empty strings and null sums are zero, not a parse failure`() {
        val json = """
            {"id": "", "attempts": "", "clicks": null, "orders": " 7 ", "revenue": "", "published": 3}
        """.trimIndent()
        val row = this.json.decodeFromString(
            eu.steamgears.commandcenter.core.model.ChannelResult.serializer(),
            json,
        )
        assertEquals(0, row.clicks)
        assertEquals(7, row.orders)
        assertEquals(3, row.published)
        assertEquals(0.0, row.revenue, 0.0)
    }
}
