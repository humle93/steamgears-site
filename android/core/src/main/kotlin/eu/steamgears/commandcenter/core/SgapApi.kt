package eu.steamgears.commandcenter.core

import eu.steamgears.commandcenter.core.model.Draft
import eu.steamgears.commandcenter.core.model.DraftStatusChange
import eu.steamgears.commandcenter.core.model.NewQueueItem
import eu.steamgears.commandcenter.core.model.QueueStatus
import eu.steamgears.commandcenter.core.model.QueueStatusChange
import eu.steamgears.commandcenter.core.model.QueuedItem
import eu.steamgears.commandcenter.core.model.Snapshot
import eu.steamgears.commandcenter.core.model.Stats

/**
 * Det Command Center bruger af `sgap/v1`: de seks endpoints appens tre skærme
 * lever af. Alt hvad der ikke er her (due, suppression, reposts, Etsy) er
 * pluginnets eget arbejde, ikke telefonens.
 */
interface SgapApi {

    /** Alt pluginnet ved, i ét kald. `days` er vinduet for tallene (1–365). */
    suspend fun snapshot(days: Int = 30, limit: Int = 100): ApiResult<Snapshot>

    /** Kladder med en given status, nyeste først. Pluginnet filtrerer på status. */
    suspend fun drafts(status: DraftStatus = DraftStatus.Approved, limit: Int = 50): ApiResult<List<Draft>>

    /** Godkend, afvis eller sæt en kladde tilbage til `pending`. */
    suspend fun setDraftStatus(id: Int, status: DraftStatus): ApiResult<DraftStatusChange>

    /** Læg et selvskrevet opslag i køen. Kræver kanal, titel og destinationslink. */
    suspend fun enqueue(item: NewQueueItem): ApiResult<QueuedItem>

    /** Marker et kø-element som publiseret eller fejlet (med en fejltekst). */
    suspend fun setQueueStatus(id: Int, status: QueueStatus, error: String = ""): ApiResult<QueueStatusChange>

    /** 30-dages tal pr. kanal og hændelsestype, plus køens fordeling. */
    suspend fun stats(days: Int = 30): ApiResult<Stats>
}

/** De fire statusser `SGAP_REST::draft_status()` accepterer. */
enum class DraftStatus(val wire: String) {
    Pending("pending"),
    Approved("approved"),
    Rejected("rejected"),
    Used("used"),
    ;

    companion object {
        fun fromWire(value: String): DraftStatus? = entries.firstOrNull { it.wire == value }
    }
}
