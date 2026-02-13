package com.sams.app.data.api

import android.content.Context
import android.content.SharedPreferences
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

/**
 * API Client Singleton
 * Manages Retrofit instance and authentication
 */
object ApiClient {
    
    private const val PREFS_NAME = "sams_prefs"
    private const val KEY_SESSION_TOKEN = "session_token"
    private const val KEY_USER_ROLE = "user_role"
    
    private var BASE_URL = "http://192.168.31.136:8000/" // Update with your server IP
    
    private var retrofit: Retrofit? = null
    private var sessionToken: String? = null
    private lateinit var prefs: SharedPreferences
    
    fun init(context: Context, baseUrl: String? = null) {
        prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        sessionToken = prefs.getString(KEY_SESSION_TOKEN, null)
        baseUrl?.let { BASE_URL = it }
    }
    
    fun setBaseUrl(url: String) {
        BASE_URL = url
        retrofit = null // Force rebuild
    }
    
    fun setSessionToken(token: String?) {
        sessionToken = token
        prefs.edit().putString(KEY_SESSION_TOKEN, token).apply()
        retrofit = null // Force rebuild with new token
    }
    
    fun getSessionToken(): String? = sessionToken
    
    fun setUserRole(role: String?) {
        prefs.edit().putString(KEY_USER_ROLE, role).apply()
    }
    
    fun getUserRole(): String? = prefs.getString(KEY_USER_ROLE, null)
    
    fun isLoggedIn(): Boolean = !sessionToken.isNullOrEmpty()
    
    fun clearSession() {
        sessionToken = null
        prefs.edit().clear().apply()
        retrofit = null
    }
    
    private fun createOkHttpClient(): OkHttpClient {
        val loggingInterceptor = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }
        
        val authInterceptor = okhttp3.Interceptor { chain ->
            val originalRequest = chain.request()
            val requestBuilder = originalRequest.newBuilder()
                .header("Content-Type", "application/json")
                .header("Accept", "application/json")
            
            // Add Authorization header if token exists
            sessionToken?.let { token ->
                requestBuilder.header("Authorization", "Bearer $token")
            }
            
            chain.proceed(requestBuilder.build())
        }
        
        return OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(loggingInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }
    
    fun getRetrofit(): Retrofit {
        if (retrofit == null) {
            retrofit = Retrofit.Builder()
                .baseUrl(BASE_URL)
                .client(createOkHttpClient())
                .addConverterFactory(GsonConverterFactory.create())
                .build()
        }
        return retrofit!!
    }
    
    val apiService: ApiService by lazy {
        getRetrofit().create(ApiService::class.java)
    }
    
    // Recreate API service (call after token change)
    fun getApiService(): ApiService {
        return getRetrofit().create(ApiService::class.java)
    }
}
