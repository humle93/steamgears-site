package eu.steamgears.commandcenter.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * En AI-kladde fra `/drafts` (rå database-række) eller `/snapshot` (beriget med
 * `promotes` og `has_image`). Derfor er begge varianter med her, med defaults:
 * listen har `post_id`, snapshot har `promotes` i stedet.
 */
@Serializable
data class Draft(
    @Serializable(with = LenientInt::class) val id: Int = 0,
    @SerialName("post_id") @Serializable(with = LenientInt::class) val postId: Int = 0,
    val channel: String = "",
    val angle: String = "",
    @SerialName("content_type") val contentType: String = "",
    val format: String = "",
    val title: String = "",
    val body: String = "",
    val hashtags: String = "",
    @SerialName("alt_text") val altText: String = "",
    val cta: String = "",
    /** `pending`, `approved`, `rejected` eller `used` — holdes som streng, så en ny status ikke vælter appen. */
    val status: String = "",
    @SerialName("ai_model") val aiModel: String = "",
    @SerialName("created_at") val createdAt: String = "",
    /** Kun i snapshot: opslaget kladden promoverer. `null` når kladden ikke peger på et opslag. */
    val promotes: Promotes? = null,
    /** Kun i snapshot: om opslaget har et fremhævet billede (Instagram/Pinterest kræver et). */
    @SerialName("has_image") val hasImage: Boolean = false,
) {
    /** Sandt når kladden peger på et opslag appen kan åbne. */
    val hasDestination: Boolean get() = !promotes?.url.isNullOrBlank()

    /** Sandt når kladden kan godkendes som den er: Instagram og Pinterest kræver et billede. */
    val isReadyToPublish: Boolean
        get() = status == "approved" && (channel !in PHOTO_CHANNELS || hasImage)

    companion object {
        /** Samme liste som `SGAP_Publer::NEEDS_PHOTO`. */
        val PHOTO_CHANNELS = setOf("instagram", "pinterest")
    }
}

@Serializable
data class Promotes(
    val title: String = "",
    val url: String = "",
)

/** Svar fra `/drafts/{id}/status`: `{"id": 31, "status": "approved"}`. */
@Serializable
data class DraftStatusChange(
    @Serializable(with = LenientInt::class) val id: Int = 0,
    val status: String = "",
)

/**
 * Et element i publiceringskøen. `tracking_url` er det link pluginnet selv
 * lægger i opslaget, så appen kan vise hvad der faktisk bliver delt.
 */
@Serializable
data class QueueItem(
    @Serializable(with = LenientInt::class) val id: Int = 0,
    @SerialName("post_id") @Serializable(with = LenientInt::class) val postId: Int = 0,
    val channel: String = "",
    val angle: String = "",
    @SerialName("content_type") val contentType: String = "",
    val format: String = "",
    @SerialName("destination_url") val destinationUrl: String = "",
    val title: String = "",
    val body: String = "",
    @SerialName("image_url") val imageUrl: String = "",
    val status: String = "",
    @Serializable(with = LenientInt::class) val attempts: Int = 0,
    @SerialName("scheduled_at") val scheduledAt: String? = null,
    @SerialName("sent_at") val sentAt: String? = null,
    @SerialName("published_at") val publishedAt: String? = null,
    val error: String = "",
    @SerialName("tracking_url") val trackingUrl: String = "",
) {
    val hasFailed: Boolean get() = status == "failed" || error.isNotBlank()
}
