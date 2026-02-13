package com.sams.app

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class SAMSApplication : Application() {
    
    override fun onCreate() {
        super.onCreate()
        createNotificationChannels()
    }
    
    private fun createNotificationChannels() {
        val notificationManager = getSystemService(NotificationManager::class.java)

        // Main notification channel
        val mainChannel = NotificationChannel(
            "sams_notifications",
            "SAMS Notifications",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Attendance and class notifications"
            enableVibration(true)
            enableLights(true)
        }

        // Class session channel
        val classChannel = NotificationChannel(
            "class_sessions",
            "Class Sessions",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Notifications about class sessions starting"
            enableVibration(true)
        }

        // Attendance reminder channel
        val attendanceChannel = NotificationChannel(
            "attendance_reminders",
            "Attendance Reminders",
            NotificationManager.IMPORTANCE_DEFAULT
        ).apply {
            description = "Reminders to mark attendance"
        }

        notificationManager.createNotificationChannels(
            listOf(mainChannel, classChannel, attendanceChannel)
        )
    }
}
