package com.sams.app.service

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.sams.app.data.api.ApiClient
import com.sams.app.data.models.FcmTokenRequest
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

/**
 * Firebase Cloud Messaging Service
 * Handles push notifications
 */
class SAMSFirebaseMessagingService : FirebaseMessagingService() {
    
    companion object {
        private const val CHANNEL_ID = "sams_notifications"
        private const val CHANNEL_NAME = "SAMS Notifications"
    }
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        
        // Register token with server if logged in
        if (ApiClient.isLoggedIn()) {
            CoroutineScope(Dispatchers.IO).launch {
                try {
                    ApiClient.getApiService().registerFcmToken(
                        FcmTokenRequest(token, "android", Build.MODEL)
                    )
                } catch (e: Exception) {
                    // Log error
                }
            }
        }
        
        // Store token locally
        getSharedPreferences("sams_prefs", Context.MODE_PRIVATE)
            .edit()
            .putString("fcm_token", token)
            .apply()
    }
    
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        
        // Get notification data
        val title = remoteMessage.notification?.title ?: remoteMessage.data["title"] ?: "SAMS"
        val body = remoteMessage.notification?.body ?: remoteMessage.data["body"] ?: ""
        val type = remoteMessage.data["type"] ?: "system"
        val notificationId = remoteMessage.data["notification_id"]?.toIntOrNull() ?: System.currentTimeMillis().toInt()
        
        showNotification(title, body, type, notificationId)
    }
    
    private fun showNotification(title: String, body: String, type: String, notificationId: Int) {
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        
        // Create notification channel for Android O+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "SAMS attendance notifications"
                enableVibration(true)
            }
            notificationManager.createNotificationChannel(channel)
        }
        
        // Create intent based on notification type
        val intent = packageManager.getLaunchIntentForPackage(packageName)?.apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            putExtra("notification_type", type)
            putExtra("notification_id", notificationId)
        }
        
        val pendingIntent = PendingIntent.getActivity(
            this,
            notificationId,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        
        // Build notification
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(android.R.drawable.ic_dialog_info) // Replace with your app icon
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()
        
        notificationManager.notify(notificationId, notification)
    }
}
