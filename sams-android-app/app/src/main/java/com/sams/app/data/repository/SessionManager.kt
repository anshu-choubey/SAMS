package com.sams.app.data.repository

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.sams.app.data.models.User
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "sams_session")

@Singleton
class SessionManager @Inject constructor(
    private val context: Context
) {
    private val json = Json { ignoreUnknownKeys = true }
    
    companion object {
        private val SESSION_TOKEN = stringPreferencesKey("session_token")
        private val USER_DATA = stringPreferencesKey("user_data")
        private val USER_ID = intPreferencesKey("user_id")
    }
    
    val userId: Flow<Int?> = context.dataStore.data.map { preferences ->
        preferences[USER_ID]
    }
    
    fun saveSession(token: String, user: User) {
        runBlocking {
            context.dataStore.edit { preferences ->
                preferences[SESSION_TOKEN] = token
                preferences[USER_DATA] = json.encodeToString(user)
                preferences[USER_ID] = user.id
            }
        }
    }
    
    fun getToken(): String? {
        return runBlocking {
            context.dataStore.data.first()[SESSION_TOKEN]
        }
    }
    
    fun getUser(): User? {
        return runBlocking {
            val userData = context.dataStore.data.first()[USER_DATA]
            userData?.let {
                try {
                    json.decodeFromString<User>(it)
                } catch (e: Exception) {
                    null
                }
            }
        }
    }
    
    fun clearSession() {
        runBlocking {
            context.dataStore.edit { preferences ->
                preferences.clear()
            }
        }
    }
    
    fun isLoggedIn(): Boolean = getToken() != null
}
