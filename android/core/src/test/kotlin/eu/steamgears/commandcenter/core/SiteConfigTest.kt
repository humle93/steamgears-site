package eu.steamgears.commandcenter.core

import kotlin.test.Test
import kotlin.test.assertEquals
import kotlin.test.assertFailsWith
import kotlin.test.assertTrue

class SiteConfigTest {

    @Test
    fun `bare host becomes https`() {
        assertEquals("https://steamgears.eu", SiteConfig.normalizeBaseUrl("steamgears.eu"))
    }

    @Test
    fun `whatever the owner pastes from the browser ends at the same site root`() {
        val variants = listOf(
            "https://steamgears.eu",
            "https://steamgears.eu/",
            "  https://steamgears.eu///  ",
            "https://steamgears.eu/wp-json",
            "https://steamgears.eu/wp-json/",
            "https://steamgears.eu/wp-json/sgap/v1",
            "https://steamgears.eu/wp-json/sgap/v1/",
            "HTTPS://Steamgears.EU/",
        )
        for (variant in variants) {
            assertEquals("https://steamgears.eu", SiteConfig.normalizeBaseUrl(variant), "for \"$variant\"")
        }
    }

    @Test
    fun `an install in a subfolder keeps its path`() {
        assertEquals("https://site.dk/shop", SiteConfig.normalizeBaseUrl("https://site.dk/shop/"))
        assertEquals(
            "https://site.dk/shop",
            SiteConfig.normalizeBaseUrl("https://site.dk/shop/wp-json/sgap/v1"),
        )
    }

    @Test
    fun `port is kept for a local development site`() {
        assertEquals("http://localhost:8080", SiteConfig.normalizeBaseUrl("http://localhost:8080/"))
    }

    @Test
    fun `rest base and endpoints have no double slashes`() {
        val config = SiteConfig.parse("https://steamgears.eu/", " owner ", "abcd efgh")
        assertEquals("https://steamgears.eu/wp-json/sgap/v1", config.restBase)
        assertEquals(
            "https://steamgears.eu/wp-json/sgap/v1/drafts/31/status",
            config.endpoint("drafts", "31", "status"),
        )
        assertEquals("owner", config.username)
    }

    @Test
    fun `application password spaces are removed before it is sent`() {
        // WordPress viser passwordet som "abcd efgh ijkl mnop"; med mellemrummene i headeren svarer det 401.
        val config = SiteConfig.parse("https://steamgears.eu", "owner", " abcd efgh ijkl mnop ")
        assertEquals("abcdefghijklmnop", config.authPassword)
    }

    @Test
    fun `plain http is refused outside localhost because the password would be readable`() {
        val error = assertFailsWith<IllegalArgumentException> {
            SiteConfig.normalizeBaseUrl("http://steamgears.eu")
        }
        assertTrue(error.message!!.contains("https://"), error.message!!)
    }

    @Test
    fun `empty and nonsense addresses are refused with an actionable message`() {
        assertTrue(
            assertFailsWith<IllegalArgumentException> { SiteConfig.normalizeBaseUrl("   ") }
                .message!!.contains("Site-adressen mangler"),
        )
        assertTrue(
            assertFailsWith<IllegalArgumentException> { SiteConfig.normalizeBaseUrl("ftp://steamgears.eu") }
                .message!!.contains("Kun http og https"),
        )
        assertTrue(
            assertFailsWith<IllegalArgumentException> { SiteConfig.normalizeBaseUrl("https://steamgears.eu/?a=1") }
                .message!!.contains("spørgsmål"),
        )
        assertTrue(
            assertFailsWith<IllegalArgumentException> { SiteConfig.normalizeBaseUrl("https://") }
                .message!!.contains("domænenavn"),
        )
    }

    @Test
    fun `missing username or password is refused before any request is made`() {
        assertTrue(
            assertFailsWith<IllegalArgumentException> {
                SiteConfig.parse("https://steamgears.eu", "  ", "abcd efgh")
            }.message!!.contains("Brugernavnet"),
        )
        assertTrue(
            assertFailsWith<IllegalArgumentException> {
                SiteConfig.parse("https://steamgears.eu", "owner", "   ")
            }.message!!.contains("Application password"),
        )
    }
}
