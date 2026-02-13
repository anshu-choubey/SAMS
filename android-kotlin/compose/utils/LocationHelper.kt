package com.sams.app.utils

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import androidx.core.content.ContextCompat
import com.google.android.gms.location.*
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.math.*

class LocationHelper(private val context: Context) {
    
    private val fusedLocationClient: FusedLocationProviderClient =
        LocationServices.getFusedLocationProviderClient(context)
    
    companion object {
        const val PROXIMITY_RADIUS_METERS = 50.0 // 50 meters radius for attendance
    }
    
    fun hasLocationPermission(): Boolean {
        return ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
    }
    
    @SuppressLint("MissingPermission")
    suspend fun getCurrentLocation(): Location? {
        if (!hasLocationPermission()) return null
        
        return suspendCancellableCoroutine { continuation ->
            fusedLocationClient.getCurrentLocation(
                Priority.PRIORITY_HIGH_ACCURACY,
                null
            ).addOnSuccessListener { location ->
                continuation.resume(location)
            }.addOnFailureListener { exception ->
                continuation.resumeWithException(exception)
            }
        }
    }
    
    @SuppressLint("MissingPermission")
    suspend fun getLastKnownLocation(): Location? {
        if (!hasLocationPermission()) return null
        
        return suspendCancellableCoroutine { continuation ->
            fusedLocationClient.lastLocation
                .addOnSuccessListener { location ->
                    continuation.resume(location)
                }
                .addOnFailureListener { exception ->
                    continuation.resumeWithException(exception)
                }
        }
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in meters
     */
    fun calculateDistance(
        lat1: Double, lon1: Double,
        lat2: Double, lon2: Double
    ): Double {
        val earthRadius = 6371000.0 // meters
        
        val dLat = Math.toRadians(lat2 - lat1)
        val dLon = Math.toRadians(lon2 - lon1)
        
        val a = sin(dLat / 2).pow(2) +
                cos(Math.toRadians(lat1)) *
                cos(Math.toRadians(lat2)) *
                sin(dLon / 2).pow(2)
        
        val c = 2 * asin(sqrt(a))
        
        return earthRadius * c
    }
    
    /**
     * Check if student is within proximity of teacher
     */
    fun isWithinProximity(
        studentLat: Double, studentLon: Double,
        teacherLat: Double, teacherLon: Double,
        radiusMeters: Double = PROXIMITY_RADIUS_METERS
    ): Boolean {
        val distance = calculateDistance(studentLat, studentLon, teacherLat, teacherLon)
        return distance <= radiusMeters
    }
    
    /**
     * Get distance to teacher location
     */
    fun getDistanceToTeacher(
        studentLat: Double, studentLon: Double,
        teacherLat: Double, teacherLon: Double
    ): Double {
        return calculateDistance(studentLat, studentLon, teacherLat, teacherLon)
    }
    
    /**
     * Request location updates for real-time tracking
     */
    @SuppressLint("MissingPermission")
    fun requestLocationUpdates(
        intervalMs: Long = 5000,
        onLocationUpdate: (Location) -> Unit
    ): LocationCallback? {
        if (!hasLocationPermission()) return null
        
        val locationRequest = LocationRequest.Builder(
            Priority.PRIORITY_HIGH_ACCURACY,
            intervalMs
        ).apply {
            setMinUpdateDistanceMeters(5f)
            setWaitForAccurateLocation(true)
        }.build()
        
        val locationCallback = object : LocationCallback() {
            override fun onLocationResult(result: LocationResult) {
                result.lastLocation?.let { location ->
                    onLocationUpdate(location)
                }
            }
        }
        
        fusedLocationClient.requestLocationUpdates(
            locationRequest,
            locationCallback,
            context.mainLooper
        )
        
        return locationCallback
    }
    
    /**
     * Stop location updates
     */
    fun stopLocationUpdates(callback: LocationCallback) {
        fusedLocationClient.removeLocationUpdates(callback)
    }
}
