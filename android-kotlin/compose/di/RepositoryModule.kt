package com.sams.app.di

import com.sams.app.data.api.ApiService
import com.sams.app.data.repository.*
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object RepositoryModule {
    
    @Provides
    @Singleton
    fun provideAuthRepository(
        apiService: ApiService,
        sessionManager: SessionManager
    ): AuthRepository = AuthRepository(apiService, sessionManager)
    
    @Provides
    @Singleton
    fun provideStudentRepository(
        apiService: ApiService
    ): StudentRepository = StudentRepository(apiService)
    
    @Provides
    @Singleton
    fun provideTeacherRepository(
        apiService: ApiService
    ): TeacherRepository = TeacherRepository(apiService)
    
    @Provides
    @Singleton
    fun provideNotificationRepository(
        apiService: ApiService
    ): NotificationRepository = NotificationRepository(apiService)
}
