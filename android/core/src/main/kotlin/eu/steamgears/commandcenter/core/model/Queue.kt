package eu.steamgears.commandcenter.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * Et opslag appen selv lægger i køen via `POST /queue`.
 *
 * Pluginnet kræver `channel`, `title` og `destination_url`; resten er valgfrit og
 * udelades af body'en når det er tomt, så WordPress' defaults gælder i stedet for
 * at appen sender tomme strenge ind og overskriver dem.
 */
data class NewQueueItem(
    val channel: String,
    val title: String,
    val destinationUrl: String,
    val body: String = "",
    val imageUrl: String = "",
    val angle: String = "",
    val contentType: String = "",
    val format: String = "",
    /** `Y-m-d H:i:s` i sitets tidszone, eller null for "så hurtigt som muligt". */
    val scheduledAt: String? = null,
    /** `draft` lægger den i køen uden at sende; tom betyder `queued`. */
    val status: String = "",
) {
    /** Felterne som pluginnet læser dem; tomme felter er ikke med. */
    fun toMap(): Map<String, String> = buildMap {
        put("channel", channel.trim())
        put("title", title.trim())
        put("destination_url", destinationUrl.trim())
        if (body.isNotBlank()) put("body", body)
        if (imageUrl.isNotBlank()) put("image_url", imageUrl.trim())
        if (angle.isNotBlank()) put("angle", angle.trim())
        if (contentType.isNotBlank()) put("content_type", contentType.trim())
        if (format.isNotBlank()) put("format", format.trim())
        if (scheduledAt != null) put("scheduled_at", scheduledAt)
        if (status.isNotBlank()) put("status", status.trim())
    }
}

/** Svar fra `POST /queue`: `{"id": 42, "tracking_url": "...", "utm_url": "..."}`. */
@Serializable
data class QueuedItem(
    @Serializable(with = LenientInt::class) val id: Int = 0,
    @SerialName("tracking_url") val trackingUrl: String = "",
    @SerialName("utm_url") val utmUrl: String = "",
)

/** De to statusser `SGAP_REST::queue_status()` accepterer. */
enum class QueueStatus(val wire: String) {
    Published("published"),
    Failed("failed"),
}

/** Svar fra `POST /queue/{id}/status`: `{"id": 42, "status": "published"}`. */
@Serializable
data class QueueStatusChange(
    @Serializable(with = LenientInt::class) val id: Int = 0,
    val status: String = "",
)

/** Svar fra `GET /stats`. Learning-delen er udeladt indtil en skærm bruger den. */
@Serializable
data class Stats(
    @Serializable(with = LenientInt::class) val days: Int = 0,
    val events: List<EventCount> = emptyList(),
    val queue: List<QueueCount> = emptyList(),
) {
    /** Antal af en hændelsestype på tværs af kanaler, fx alle `click` i vinduet. */
    fun total(eventType: String): Int =
        events.filter { it.eventType == eventType }.sumOf { it.count }
}

@Serializable
data class EventCount(
    val channel: String = "",
    @SerialName("event_type") val eventType: String = "",
    @Serializable(with = LenientInt::class) val count: Int = 0,
    @Serializable(with = LenientDouble::class) val value: Double = 0.0,
)

@Serializable
data class QueueCount(
    val status: String = "",
    @Serializable(with = LenientInt::class) val count: Int = 0,
)
