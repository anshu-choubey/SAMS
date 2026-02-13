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
import com.sams.app.R
import com.sams.app.app.MainActivity
import com.sams.app.data.api.ApiService
import com.sams.app.data.models.FcmRegisterRequest
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import javax.inject.Inject

@AndroidEntryPoint
class SAMSFirebaseMessagingService : FirebaseMessagingService() {
    
    @Inject
    lateinit var apiService: ApiService
    
    companion object {
        private const val CHANNEL_ID = "sams_notifications"
        private const val CHANNEL_NAME = "SAMS Notifications"
    }
    
    override fun onNewToken(token: String) {
        super.onNewToken(token)
        // Register the new token with the server
        CoroutineScope(Dispatchers.IO).launch {
            try {
                apiService.registerFcmToken(FcmRegisterRequest(token))
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }
    
    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        super.onMessageReceived(remoteMessage)
        
        // Check if message contains a notification payload
        remoteMessage.notification?.let { notification ->
            showNotification(
                title = notification.title ?: "SAMS",
                body = notification.body ?: "",
                data = remoteMessage.data
            )
        }
        
        // Check if message contains data payload
        if (remoteMessage.data.isNotEmpty()) {
            handleDataMessage(remoteMessage.data)
        }
    }
    
    private fun showNotification(
        title: String,
        body: String,
        data: Map<String, String>
    ) {
        createNotificationChannel()
        
        val intent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            // Pass notification data to activity
            data.forEach { (key, value) -> putExtra(key, value) }
        }
        
        val pendingIntent = PendingIntent.getActivity(
            this,
            System.currentTimeMillis().toInt(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .setContentIntent(pendingIntent)
            .build()
        
        val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        notificationManager.notify(System.currentTimeMillis().toInt(), notification)
    }
    
    private fun handleDataMessage(data: Map<String, String>) {
        val type = data["type"] ?: return
        
        when (type) {
            "session_started" -> {
                // A teacher started a session - notify student
                val subjectName = data["subject_name"] ?: "Class"
                val teacherName = data["teacher_name"] ?: "Teacher"
                showNotification(
                    title = "Class Started!",
                    body = "$subjectName by $teacherName is now accepting attendance",
                    data = data
                )
            }
            "session_ended" -> {
                // Session ended
                val subjectName = data["subject_name"] ?: "Class"
                showNotification(
                    title = "Class Ended",
                    body = "$subjectName attendance session has ended",
                    data = data
                )
            }
            "attendance_marked" -> {
                // Attendance was marked successfully
                val subjectName = data["subject_name"] ?: "Class"
                showNotification(
                    title = "Attendance Recorded",
                    body = "Your attendance for $subjectName has been marked",
                    data = data
                )
            }
            "low_attendance_alert" -> {
                // Low attendance warning
                val percentage = data["percentage"] ?: "N/A"
                showNotification(
                    title = "Low Attendance Alert",
                    body = "Your attendance is at $percentage%. Please attend more classes.",
                    data = data
                )
            }
            "announcement" -> {
                // General announcement
                val title = data["title"] ?: "Announcement"
                val message = data["message"] ?: ""
                showNotification(
                    title = title,
                    body = message,
                    data = data
                )
            }
        }
    }
    
    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                CHANNEL_NAME,
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Notifications for SAMS attendance system"
                enableVibration(true)
                enableLights(true)
            }
            
            val notificationManager = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
            notificationManager.createNotificationChannel(channel)
        }
    }
}
