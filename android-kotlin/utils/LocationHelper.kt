package com.sams.app.utils

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import androidx.core.content.ContextCompat
import com.google.android.gms.location.FusedLocationProviderClient
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

/**
 * GPS Location Helper
 * Handles location services for attendance verification
 */
class LocationHelper(private val context: Context) {
    
    companion object {
        const val GPS_PROXIMITY_RADIUS = 50.0 // meters
    }
    
    private val fusedLocationClient: FusedLocationProviderClient =
        LocationServices.getFusedLocationProviderClient(context)
    
    /**
     * Check if location permissions are granted
     */
    fun hasLocationPermission(): Boolean {
        return ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
    }
    
    /**
     * Get current location
     */
    suspend fun getCurrentLocation(): LocationResult = suspendCancellableCoroutine { cont ->
        if (!hasLocationPermission()) {
            cont.resume(LocationResult.PermissionDenied)
            return@suspendCancellableCoroutine
        }
        
        val cancellationTokenSource = CancellationTokenSource()
        
        try {
            fusedLocationClient.getCurrentLocation(
                Priority.PRIORITY_HIGH_ACCURACY,
                cancellationTokenSource.token
            ).addOnSuccessListener { location: Location? ->
                if (location != null) {
                    cont.resume(LocationResult.Success(location))
                } else {
                    cont.resume(LocationResult.LocationUnavailable)
                }
            }.addOnFailureListener { e ->
                cont.resume(LocationResult.Error(e.message ?: "Location error"))
            }
        } catch (e: SecurityException) {
            cont.resume(LocationResult.PermissionDenied)
        }
        
        cont.invokeOnCancellation {
            cancellationTokenSource.cancel()
        }
    }
    
    /**
     * Calculate distance between two locations in meters
     */
    fun calculateDistance(
        lat1: Double, lon1: Double,
        lat2: Double, lon2: Double
    ): Float {
        val results = FloatArray(1)
        Location.distanceBetween(lat1, lon1, lat2, lon2, results)
        return results[0]
    }
    
    /**
     * Check if student is within proximity of teacher
     */
    fun isWithinProximity(
        studentLat: Double, studentLon: Double,
        teacherLat: Double, teacherLon: Double,
        radiusMeters: Double = GPS_PROXIMITY_RADIUS
    ): ProximityResult {
        val distance = calculateDistance(studentLat, studentLon, teacherLat, teacherLon)
        return ProximityResult(
            isWithin = distance <= radiusMeters,
            distanceMeters = distance.toDouble(),
            maxRadius = radiusMeters
        )
    }
}

// ==================== Result Classes ====================

sealed class LocationResult {
    data class Success(val location: Location) : LocationResult()
    object PermissionDenied : LocationResult()
    object LocationUnavailable : LocationResult()
    data class Error(val message: String) : LocationResult()
}

data class ProximityResult(
    val isWithin: Boolean,
    val distanceMeters: Double,
    val maxRadius: Double
)
