package com.sams.app

import android.app.Application
import com.sams.app.data.api.ApiClient

/**
 * SAMS Application Class
 * Initialize API client and other singletons
 */
class SAMSApplication : Application() {
    
    override fun onCreate() {
        super.onCreate()
        
        // Initialize API Client
        ApiClient.init(this, BASE_URL)
    }
    
    companion object {
        // Update this with your server IP/URL
        const val BASE_URL = "http://192.168.31.136:8000/"
    }
}
