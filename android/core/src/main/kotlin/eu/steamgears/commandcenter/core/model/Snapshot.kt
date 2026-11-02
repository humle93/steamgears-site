package eu.steamgears.commandcenter.core.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * Alt pluginnet ved, i ét read-only kald: `/wp-json/sgap/v1/snapshot`.
 *
 * Kun det appens skærme bruger er modelleret. Resten (daily-serien,
 * konverteringer, learning, indstillinger) ignoreres bevidst — `ignoreUnknownKeys`
 * gør det sikkert, og en ny sektion i pluginnet vælter ikke appen.
 *
 * Felterne har defaults, fordi en ældre plugin-version ikke har dem alle.
 */
@Serializable
data class Snapshot(
    @SerialName("generated_at") val generatedAt: String = "",
    val site: Site = Site(),
    val version: String = "",
    @SerialName("missing_tables") val missingTables: List<String> = emptyList(),
    val window: Window = Window(),
    val status: Status = Status(),
    val counts: Counts = Counts(),
    val results: Results = Results(),
    val queue: List<QueueItem> = emptyList(),
    val drafts: List<Draft> = emptyList(),
) {
    /**
     * Sandt når autopiloten er tændt, ikke er sat på pause, og der er plads i
     * dagens kvote. Pause-årsagen skal tælles med: `can_send_now` er kvoten,
     * ikke et løfte om at der faktisk bliver sendt (fx i quiet hours).
     */
    val isSending: Boolean
        get() = status.autopilot && status.sendingPausedBecause == null && status.canSendNow > 0

    /**
     * Det ene spørgsmål skærmen skal svare på: sendes der, og hvis ikke hvorfor.
     * Rækkefølgen er med vilje — en pause-årsag slår en tom kvote.
     */
    val headline: String
        get() = when {
            !status.autopilot -> "Autopilot er slået fra"
            status.sendingPausedBecause != null -> "Sat på pause: ${status.sendingPausedBecause}"
            status.canSendNow == 0 -> "Dagens kvote er brugt"
            else -> "Sender — ${status.canSendNow} klar nu"
        }

    /** Sandt når plugin'et mangler tabeller; så er tomme lister ikke det samme som "alt er fint". */
    val isBroken: Boolean get() = missingTables.isNotEmpty()
}

@Serializable
data class Site(
    val name: String = "",
    val url: String = "",
    val timezone: String = "",
    @SerialName("local_time") val localTime: String = "",
)

@Serializable
data class Window(
    @Serializable(with = LenientInt::class) val days: Int = 0,
    val since: String = "",
)

@Serializable
data class Status(
    val autopilot: Boolean = false,
    @SerialName("full_auto") val fullAuto: Boolean = false,
    @SerialName("publish_mode") val publishMode: String = "",
    val channels: List<String> = emptyList(),
    @SerialName("publer_configured") val publerConfigured: Boolean = false,
    @SerialName("ai_configured") val aiConfigured: Boolean = false,
    @SerialName("ai_provider") val aiProvider: String = "",
    @SerialName("ai_model") val aiModel: String = "",
    /** Menneskelig årsag fra pluginnet når der ikke sendes, ellers null. */
    @SerialName("sending_paused_because") val sendingPausedBecause: String? = null,
    @SerialName("can_send_now") val canSendNow: Int = 0,
    val limits: Limits = Limits(),
    @SerialName("cron_lag_minutes") val cronLagMinutes: Int = 0,
)

@Serializable
data class Limits(
    @SerialName("daily_cap") val dailyCap: Int = 0,
    @SerialName("batch_size") val batchSize: Int = 0,
    /** fx `23:00-7:00`, som pluginnet selv formaterer den. */
    @SerialName("quiet_hours") val quietHours: String = "",
)

@Serializable
data class Counts(
    val queue: Map<String, Int> = emptyMap(),
    val drafts: Map<String, Int> = emptyMap(),
)

@Serializable
data class Results(
    @SerialName("by_channel") val byChannel: List<ChannelResult> = emptyList(),
    @Serializable(with = LenientDouble::class) val revenue: Double = 0.0,
    @Serializable(with = LenientDouble::class) val budget: Double = 0.0,
    @Serializable(with = LenientDouble::class) val roi: Double = 0.0,
    @Serializable(with = LenientDouble::class) val roas: Double = 0.0,
)

/** Tallene kommer som strenge fra MySQL, derfor de tolerante serializers. */
@Serializable
data class ChannelResult(
    val channel: String = "",
    @Serializable(with = LenientInt::class) val published: Int = 0,
    @Serializable(with = LenientInt::class) val clicks: Int = 0,
    @Serializable(with = LenientInt::class) val orders: Int = 0,
    @Serializable(with = LenientDouble::class) val revenue: Double = 0.0,
)
