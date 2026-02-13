package com.sams.app.data.repository

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import com.sams.app.data.models.User
import dagger.hilt.android.qualifiers.ApplicationContext
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
    @ApplicationContext private val context: Context
) {
    private val json = Json { ignoreUnknownKeys = true }
    
    companion object {
        private val TOKEN_KEY = stringPreferencesKey("auth_token")
        private val USER_KEY = stringPreferencesKey("user_data")
    }
    
    suspend fun saveSession(token: String, user: User) {
        context.dataStore.edit { preferences ->
            preferences[TOKEN_KEY] = token
            preferences[USER_KEY] = json.encodeToString(user)
        }
    }
    
    suspend fun clearSession() {
        context.dataStore.edit { preferences ->
            preferences.remove(TOKEN_KEY)
            preferences.remove(USER_KEY)
        }
    }
    
    fun getToken(): String? {
        return runBlocking {
            context.dataStore.data.map { preferences ->
                preferences[TOKEN_KEY]
            }.first()
        }
    }
    
    fun getUser(): User? {
        return runBlocking {
            context.dataStore.data.map { preferences ->
                preferences[USER_KEY]?.let { 
                    try {
                        json.decodeFromString<User>(it)
                    } catch (e: Exception) {
                        null
                    }
                }
            }.first()
        }
    }
    
    suspend fun getTokenAsync(): String? {
        return context.dataStore.data.map { preferences ->
            preferences[TOKEN_KEY]
        }.first()
    }
    
    suspend fun getUserAsync(): User? {
        return context.dataStore.data.map { preferences ->
            preferences[USER_KEY]?.let {
                try {
                    json.decodeToString<User>(it)
                } catch (e: Exception) {
                    null
                }
            }
        }.first()
    }
}
