package eu.steamgears.commandcenter.core

/**
 * Udfaldet af et kald. Fejl er værdier, ikke exceptions, så en skærm kan vise
 * hvad der gik galt uden at pakke alt ind i try/catch.
 *
 * Beskederne er skrevet til ejeren, ikke til en log: de siger hvad der skal
 * gøres ved det, fx at application password skal laves i WordPress.
 */
sealed class ApiResult<out T> {

    data class Ok<out T>(val value: T) : ApiResult<T>()

    sealed class Failure : ApiResult<Nothing>() {
        abstract val message: String

        /** 401/403: WordPress kender ikke login eller vil ikke lukke det ind. */
        data class Unauthorized(override val message: String) : Failure()

        /** Svar fra WordPress med en fejlkode, fx `sgap_invalid` eller en 500-side. */
        data class Http(
            val status: Int,
            val code: String,
            override val message: String,
        ) : Failure()

        /** Nettet, DNS eller tidsgrænsen svigtede — intet svar at læse. */
        data class Network(override val message: String, val cause: Throwable? = null) : Failure()

        /** Der kom svar, men det var ikke det JSON klienten forventede. */
        data class Parse(override val message: String, val cause: Throwable? = null) : Failure()
    }

    val isOk: Boolean get() = this is Ok

    fun valueOrNull(): T? = (this as? Ok)?.value
}

/** Kort besked til en skærm eller en Snackbar. */
val ApiResult<*>.errorMessage: String?
    get() = (this as? ApiResult.Failure)?.message
