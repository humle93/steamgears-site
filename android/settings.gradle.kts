pluginManagement {
    repositories {
        gradlePluginPortal()
        mavenCentral()
    }
}

dependencyResolutionManagement {
    repositories {
        mavenCentral()
    }
}

rootProject.name = "steamgears-command-center"

// :core er ren Kotlin/JVM uden Android-afhængigheder, så klienten kan testes
// på en almindelig JVM (og i CI) uden Android SDK. :app kommer i M2.
include(":core")
