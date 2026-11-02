package eu.steamgears.commandcenter.core.model

import kotlinx.serialization.KSerializer
import kotlinx.serialization.descriptors.PrimitiveKind
import kotlinx.serialization.descriptors.PrimitiveSerialDescriptor
import kotlinx.serialization.descriptors.SerialDescriptor
import kotlinx.serialization.encoding.Decoder
import kotlinx.serialization.encoding.Encoder
import kotlinx.serialization.json.JsonDecoder
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.intOrNull

/**
 * WordPress' `$wpdb` giver MySQL-kolonner tilbage som **strenge**, også
 * `id` og `COUNT(*)`. `SUM()` over nul rækker giver `null`. En klient der
 * forventer tal i JSON ville fejle på hver anden række fra det plugin.
 *
 * Disse serializers læser begge former og bruger 0 når feltet mangler,
 * er `null` eller tomt — en tælling der ikke findes er nul, ikke en fejl.
 */
internal object LenientInt : KSerializer<Int> {
    override val descriptor: SerialDescriptor =
        PrimitiveSerialDescriptor("LenientInt", PrimitiveKind.INT)

    override fun deserialize(decoder: Decoder): Int {
        val element = (decoder as? JsonDecoder)?.decodeJsonElement() ?: return decoder.decodeInt()
        if (element is JsonNull || element !is JsonPrimitive) return 0
        val primitive = element
        return if (primitive.isString) {
            primitive.content.trim().toIntOrNull() ?: primitive.content.trim().toDoubleOrNull()?.toInt() ?: 0
        } else {
            primitive.intOrNull ?: 0
        }
    }

    override fun serialize(encoder: Encoder, value: Int) = encoder.encodeInt(value)
}

internal object LenientLong : KSerializer<Long> {
    override val descriptor: SerialDescriptor =
        PrimitiveSerialDescriptor("LenientLong", PrimitiveKind.LONG)

    override fun deserialize(decoder: Decoder): Long {
        val element = (decoder as? JsonDecoder)?.decodeJsonElement() ?: return decoder.decodeLong()
        if (element is JsonNull || element !is JsonPrimitive) return 0L
        val primitive = element
        return if (primitive.isString) {
            primitive.content.trim().toLongOrNull() ?: primitive.content.trim().toDoubleOrNull()?.toLong() ?: 0L
        } else {
            primitive.content.toLongOrNull() ?: 0L
        }
    }

    override fun serialize(encoder: Encoder, value: Long) = encoder.encodeLong(value)
}

internal object LenientDouble : KSerializer<Double> {
    override val descriptor: SerialDescriptor =
        PrimitiveSerialDescriptor("LenientDouble", PrimitiveKind.DOUBLE)

    override fun deserialize(decoder: Decoder): Double {
        val element = (decoder as? JsonDecoder)?.decodeJsonElement() ?: return decoder.decodeDouble()
        if (element is JsonNull || element !is JsonPrimitive) return 0.0
        val primitive = element
        return primitive.content.trim().replace(',', '.').toDoubleOrNull() ?: 0.0
    }

    override fun serialize(encoder: Encoder, value: Double) = encoder.encodeDouble(value)
}
