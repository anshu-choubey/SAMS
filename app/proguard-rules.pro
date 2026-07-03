# Proguard rules for SAMS Android App
-keep class com.sams.app.data.models.** { *; }
-keep class com.sams.app.data.api.** { *; }
-keepclassmembers class ** {
    @com.google.gson.annotations.SerializedName <fields>;
}
-keep class com.google.gson.** { *; }
-keep interface com.google.gson.** { *; }
