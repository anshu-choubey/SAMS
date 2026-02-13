package com.sams.app.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.sams.app.MainActivity
import com.sams.app.R
import com.sams.app.data.repository.AuthRepository
import com.sams.app.data.repository.SessionManager
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class SAMSFirebaseMessagingService : FirebaseMessagingService() {
    
    @Inject
    lateinit var authRepository: AuthRepository
    
    @Inject
    lateinit var sessionManager: SessionManager
    
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        
        // Register the new token with the server
        serviceScope.launch {
            try {
                val userId = sessionManager.userId.first()
                if (userId != null) {
                    authRepository.registerFcmToken(token)
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }
    
    override fun onMessageReceived(message: RemoteMessage) {
        super.onMessageReceived(message)
        
        // Handle data payload
        val data = message.data
        val title = data["title"] ?: message.notification?.title ?: "SAMS"
        val body = data["body"] ?: message.notification?.body ?: ""
        val type = data["type"] ?: "general"
        
        sendNotification(title, body, type, data)
    }
    
    private fun sendNotification(
        title: String,
        body: String,
        type: String,
        data: Map<String, String>
    ) {
        val channelId = when (type) {
            "attendance" -> CHANNEL_ATTENDANCE
            "class_started", "class_ended" -> CHANNEL_CLASS
            "alert" -> CHANNEL_ALERTS
            else -> CHANNEL_GENERAL
        }
        
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            data.forEach { (key, value) ->
                putExtra(key, value)
            }
        }
        
        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        
        val defaultSoundUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        
        val notificationBuilder = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setAutoCancel(true)
            .setSound(defaultSoundUri)
            .setContentIntent(pendingIntent)
            .setPriority(
                when (type) {
                    "alert" -> NotificationCompat.PRIORITY_HIGH
                    "attendance" -> NotificationCompat.PRIORITY_DEFAULT
                    else -> NotificationCompat.PRIORITY_DEFAULT
                }
            )
            .setStyle(
                NotificationCompat.BigTextStyle().bigText(body)
            )
            .setCategory(
                when (type) {
                    "alert" -> NotificationCompat.CATEGORY_ALARM
                    "class_started" -> NotificationCompat.CATEGORY_EVENT
                    else -> NotificationCompat.CATEGORY_MESSAGE
                }
            )
        
        val notificationManager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        
        // Create channel for Android O+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            createNotificationChannels(notificationManager)
        }
        
        notificationManager.notify(System.currentTimeMillis().toInt(), notificationBuilder.build())
    }
    
    private fun createNotificationChannels(notificationManager: NotificationManager) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channels = listOf(
                NotificationChannel(
                    CHANNEL_GENERAL,
                    "General Notifications",
                    NotificationManager.IMPORTANCE_DEFAULT
                ).apply {
                    description = "General app notifications"
                },
                NotificationChannel(
                    CHANNEL_ATTENDANCE,
                    "Attendance Updates",
                    NotificationManager.IMPORTANCE_DEFAULT
                ).apply {
                    description = "Notifications about attendance marking"
                },
                NotificationChannel(
                    CHANNEL_CLASS,
                    "Class Updates",
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "Notifications about class sessions"
                },
                NotificationChannel(
                    CHANNEL_ALERTS,
                    "Alerts",
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "Important alerts and warnings"
                }
            )
            
            channels.forEach { channel ->
                notificationManager.createNotificationChannel(channel)
            }
        }
    }
    
    companion object {
        const val CHANNEL_GENERAL = "sams_general"
        const val CHANNEL_ATTENDANCE = "sams_attendance"
        const val CHANNEL_CLASS = "sams_class"
        const val CHANNEL_ALERTS = "sams_alerts"
    }
}
