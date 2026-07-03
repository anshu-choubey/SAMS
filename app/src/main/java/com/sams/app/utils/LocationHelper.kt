package com.sams.app.utils

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.os.Build
import android.provider.Settings
import androidx.compose.ui.graphics.Color
import androidx.core.content.ContextCompat
import com.google.android.gms.location.*
import com.google.android.gms.tasks.CancellationTokenSource
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import timber.log.Timber
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.math.*

/**
 * Enhanced Location Helper with anti-spoofing measures
 * 
 * Features:
 * - Mock location detection
 * - Accuracy validation
 * - Location staleness check
 * - Provider validation
 * - Distance calculation with altitude
 */
class LocationHelper(private val context: Context) {

    companion object {
        private const val TAG = "LocationHelper"
        const val DEFAULT_PROXIMITY_RADIUS_METERS = 50.0
        private const val LOCATION_TIMEOUT_MS = 20_000L
        
        // Accuracy thresholds
        private const val ACCURACY_EXCELLENT = 10f
        private const val ACCURACY_GOOD = 25f
        private const val ACCURACY_ACCEPTABLE = 50f
        private const val ACCURACY_MAX_ALLOWED = 100f  // Reject worse than this
        
        // Anti-spoofing
        private const val MAX_LOCATION_AGE_MS = 30_000L  // 30 seconds
        private const val MIN_SATELLITE_COUNT = 4       // Minimum GPS satellites
    }

    private val fusedLocationClient: FusedLocationProviderClient =
        LocationServices.getFusedLocationProviderClient(context)
    
    private val locationManager: LocationManager? =
        context.getSystemService(Context.LOCATION_SERVICE) as? LocationManager

    // ─────────────────────────────────────────────────────────────────────────────
    // RESULT TYPES
    // ─────────────────────────────────────────────────────────────────────────────

    sealed class LocationResult {
        data class Success(
            val location: Location,
            val accuracy: LocationAccuracy,
            val isMock: Boolean = false,
            val ageMs: Long = 0
        ) : LocationResult()

        data class Error(
            val message: String,
            val type: ErrorType
        ) : LocationResult()
    }

    enum class LocationAccuracy {
        EXCELLENT, GOOD, ACCEPTABLE, POOR;

        fun isUsable(): Boolean = this != POOR

        fun label(): String = when (this) {
            EXCELLENT -> "Excellent (±10m)"
            GOOD -> "Good (±25m)"
            ACCEPTABLE -> "Acceptable (±50m)"
            POOR -> "Poor - move to open area"
        }

        fun color(): Color = when (this) {
            EXCELLENT -> Color(0xFF4CAF50)
            GOOD -> Color(0xFF8BC34A)
            ACCEPTABLE -> Color(0xFFFFC107)
            POOR -> Color(0xFFF44336)
        }
    }

    enum class ErrorType {
        NO_PERMISSION,
        GPS_DISABLED,
        TIMEOUT,
        MOCK_DETECTED,
        TOO_INACCURATE,
        STALE_LOCATION,
        UNAVAILABLE,
        UNKNOWN
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // PERMISSION CHECKS
    // ─────────────────────────────────────────────────────────────────────────────

    fun hasFineLocationPermission(): Boolean =
        ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED

    fun hasCoarseLocationPermission(): Boolean =
        ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_COARSE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED

    fun hasAnyLocationPermission(): Boolean =
        hasFineLocationPermission() || hasCoarseLocationPermission()

    // ─────────────────────────────────────────────────────────────────────────────
    // GPS STATUS CHECKS
    // ─────────────────────────────────────────────────────────────────────────────

    fun isGpsEnabled(): Boolean {
        return try {
            locationManager?.isProviderEnabled(LocationManager.GPS_PROVIDER) == true
        } catch (e: Exception) {
            false
        }
    }

    fun isNetworkLocationEnabled(): Boolean {
        return try {
            locationManager?.isProviderEnabled(LocationManager.NETWORK_PROVIDER) == true
        } catch (e: Exception) {
            false
        }
    }

    fun isAnyLocationProviderEnabled(): Boolean = isGpsEnabled() || isNetworkLocationEnabled()

    // ─────────────────────────────────────────────────────────────────────────────
    // MOCK LOCATION DETECTION
    // ─────────────────────────────────────────────────────────────────────────────

    @SuppressLint("ObsoleteSdkInt")
    fun isMockLocationEnabled(): Boolean {
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                // Modern devices: check if any mock location app is set
                false // Can't directly check without actual location
            } else {
                // Older devices: check developer settings
                Settings.Secure.getString(
                    context.contentResolver,
                    Settings.Secure.ALLOW_MOCK_LOCATION
                ) == "1"
            }
        } catch (e: Exception) {
            false
        }
    }

    private fun isLocationMocked(location: Location): Boolean {
        return try {
            when {
                // API 31+ - most reliable
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> location.isMock
                // API 18+ - check isFromMockProvider
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR2 -> {
                    @Suppress("DEPRECATION")
                    location.isFromMockProvider
                }
                else -> false
            }
        } catch (e: Exception) {
            Timber.tag(TAG).w("Mock detection failed: ${e.message}")
            false
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // HIGH-ACCURACY LOCATION FETCH (with validation)
    // ─────────────────────────────────────────────────────────────────────────────

    @SuppressLint("MissingPermission")
    suspend fun getValidatedLocation(
        allowMock: Boolean = false,
        maxAccuracyMeters: Float = ACCURACY_MAX_ALLOWED,
        maxAgeMs: Long = MAX_LOCATION_AGE_MS
    ): LocationResult {
        // Permission check
        if (!hasAnyLocationPermission()) {
            return LocationResult.Error(
                "Location permission not granted",
                ErrorType.NO_PERMISSION
            )
        }

        // GPS status check
        if (!isAnyLocationProviderEnabled()) {
            return LocationResult.Error(
                "Please enable GPS/Location services",
                ErrorType.GPS_DISABLED
            )
        }

        val cts = CancellationTokenSource()

        return try {
            val location = withTimeoutOrNull(LOCATION_TIMEOUT_MS) {
                suspendCancellableCoroutine { continuation ->
                    continuation.invokeOnCancellation { cts.cancel() }
                    
                    // Request high accuracy location
                    val request = CurrentLocationRequest.Builder()
                        .setPriority(Priority.PRIORITY_HIGH_ACCURACY)
                        .setDurationMillis(LOCATION_TIMEOUT_MS - 2000)
                        .setMaxUpdateAgeMillis(maxAgeMs)
                        .build()
                    
                    fusedLocationClient.getCurrentLocation(request, cts.token)
                        .addOnSuccessListener { loc ->
                            continuation.resume(loc)
                        }
                        .addOnFailureListener { e ->
                            continuation.resumeWithException(e)
                        }
                }
            }

            when {
                location == null -> {
                    // Try fallback to last known location
                    val fallback = getLastKnownLocation()
                    if (fallback != null && validateLocation(fallback, allowMock, maxAccuracyMeters, maxAgeMs)) {
                        val age = System.currentTimeMillis() - fallback.time
                        LocationResult.Success(
                            location = fallback,
                            accuracy = fallback.toAccuracyEnum(),
                            isMock = isLocationMocked(fallback),
                            ageMs = age
                        )
                    } else {
                        LocationResult.Error(
                            "Location timed out. Move to an open area.",
                            ErrorType.TIMEOUT
                        )
                    }
                }
                else -> {
                    // Validate the location
                    val validationResult = validateLocationWithReason(location, allowMock, maxAccuracyMeters, maxAgeMs)
                    if (validationResult.first) {
                        val age = System.currentTimeMillis() - location.time
                        LocationResult.Success(
                            location = location,
                            accuracy = location.toAccuracyEnum(),
                            isMock = isLocationMocked(location),
                            ageMs = age
                        )
                    } else {
                        LocationResult.Error(validationResult.second, validationResult.third)
                    }
                }
            }
        } catch (e: SecurityException) {
            LocationResult.Error("Location permission denied", ErrorType.NO_PERMISSION)
        } catch (e: Exception) {
            Timber.tag(TAG).e("Location error: ${e.message}")
            LocationResult.Error(e.message ?: "Unknown location error", ErrorType.UNKNOWN)
        } finally {
            cts.cancel()
        }
    }

    private fun validateLocation(
        location: Location,
        allowMock: Boolean,
        maxAccuracy: Float,
        maxAge: Long
    ): Boolean {
        return validateLocationWithReason(location, allowMock, maxAccuracy, maxAge).first
    }

    private fun validateLocationWithReason(
        location: Location,
        allowMock: Boolean,
        maxAccuracy: Float,
        maxAge: Long
    ): Triple<Boolean, String, ErrorType> {
        // 1. Mock detection
        if (!allowMock && isLocationMocked(location)) {
            Timber.tag(TAG).w("Mock location detected!")
            return Triple(false, "Fake location detected. Disable mock location apps.", ErrorType.MOCK_DETECTED)
        }

        // 2. Accuracy check
        if (location.accuracy > maxAccuracy) {
            Timber.tag(TAG).w("Location too inaccurate: ${location.accuracy}m")
            return Triple(false, "Location too inaccurate (${location.accuracy.toInt()}m). Move to open area.", ErrorType.TOO_INACCURATE)
        }

        // 3. Staleness check
        val age = System.currentTimeMillis() - location.time
        if (age > maxAge) {
            Timber.tag(TAG).w("Location too old: ${age}ms")
            return Triple(false, "Location is stale. Please wait for GPS update.", ErrorType.STALE_LOCATION)
        }

        return Triple(true, "OK", ErrorType.UNKNOWN)
    }

    /** Convenience wrapper — returns null on any failure */
    @SuppressLint("MissingPermission")
    suspend fun getCurrentLocation(): Location? =
        (getValidatedLocation() as? LocationResult.Success)?.location

    /** Legacy wrapper for existing code */
    @SuppressLint("MissingPermission")
    suspend fun getCurrentLocationResult(): LocationResult = getValidatedLocation()

    // ─────────────────────────────────────────────────────────────────────────────
    // LAST KNOWN LOCATION (fast, may be stale)
    // ─────────────────────────────────────────────────────────────────────────────

    @SuppressLint("MissingPermission")
    suspend fun getLastKnownLocation(): Location? {
        if (!hasAnyLocationPermission()) return null
        return suspendCancellableCoroutine { continuation ->
            fusedLocationClient.lastLocation
                .addOnSuccessListener { continuation.resume(it) }
                .addOnFailureListener { continuation.resumeWithException(it) }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // DISTANCE & PROXIMITY CALCULATION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Calculate distance using Haversine formula (accounts for Earth's curvature)
     */
    fun calculateDistance(
        lat1: Double, lon1: Double,
        lat2: Double, lon2: Double
    ): Double {
        val earthRadius = 6_371_000.0 // meters
        val dLat = (lat2 - lat1).toRadians()
        val dLon = (lon2 - lon1).toRadians()
        val a = sin(dLat / 2).pow(2) +
                cos(lat1.toRadians()) * cos(lat2.toRadians()) *
                sin(dLon / 2).pow(2)
        return earthRadius * 2 * asin(sqrt(a))
    }

    /**
     * Calculate distance accounting for altitude difference
     */
    fun calculateDistance3D(
        lat1: Double, lon1: Double, alt1: Double,
        lat2: Double, lon2: Double, alt2: Double
    ): Double {
        val horizontalDistance = calculateDistance(lat1, lon1, lat2, lon2)
        val altitudeDiff = abs(alt1 - alt2)
        return sqrt(horizontalDistance.pow(2) + altitudeDiff.pow(2))
    }

    /**
     * Check if student is within proximity of teacher
     * Accounts for GPS accuracy in the calculation
     */
    fun isWithinProximity(
        studentLat: Double, studentLon: Double,
        teacherLat: Double, teacherLon: Double,
        radiusMeters: Double = DEFAULT_PROXIMITY_RADIUS_METERS,
        studentAccuracy: Float = 0f,
        teacherAccuracy: Float = 0f
    ): Boolean {
        val distance = calculateDistance(studentLat, studentLon, teacherLat, teacherLon)
        // Effective radius accounts for GPS uncertainty
        val effectiveRadius = radiusMeters + (studentAccuracy + teacherAccuracy) / 2
        return distance <= effectiveRadius
    }

    fun getDistanceToTeacher(
        studentLat: Double, studentLon: Double,
        teacherLat: Double, teacherLon: Double
    ): Double = calculateDistance(studentLat, studentLon, teacherLat, teacherLon)

    // ─────────────────────────────────────────────────────────────────────────────
    // PROXIMITY RESULT WITH DETAILS
    // ─────────────────────────────────────────────────────────────────────────────

    data class ProximityResult(
        val isWithinRange: Boolean,
        val distanceMeters: Double,
        val radiusMeters: Double,
        val accuracy: LocationAccuracy,
        val message: String
    )

    suspend fun checkProximityToTeacher(
        teacherLat: Double,
        teacherLon: Double,
        radiusMeters: Double = DEFAULT_PROXIMITY_RADIUS_METERS
    ): ProximityResult {
        val locationResult = getValidatedLocation()
        
        return when (locationResult) {
            is LocationResult.Success -> {
                val distance = calculateDistance(
                    locationResult.location.latitude,
                    locationResult.location.longitude,
                    teacherLat, teacherLon
                )
                val withinRange = distance <= radiusMeters
                
                ProximityResult(
                    isWithinRange = withinRange,
                    distanceMeters = distance,
                    radiusMeters = radiusMeters,
                    accuracy = locationResult.accuracy,
                    message = if (withinRange) {
                        "Within range (${distance.toInt()}m)"
                    } else {
                        "Out of range: ${distance.toInt()}m (max ${radiusMeters.toInt()}m)"
                    }
                )
            }
            is LocationResult.Error -> {
                ProximityResult(
                    isWithinRange = false,
                    distanceMeters = -1.0,
                    radiusMeters = radiusMeters,
                    accuracy = LocationAccuracy.POOR,
                    message = locationResult.message
                )
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────────────────────────────────────

    private fun Location.toAccuracyEnum(): LocationAccuracy = when {
        accuracy <= ACCURACY_EXCELLENT -> LocationAccuracy.EXCELLENT
        accuracy <= ACCURACY_GOOD -> LocationAccuracy.GOOD
        accuracy <= ACCURACY_ACCEPTABLE -> LocationAccuracy.ACCEPTABLE
        else -> LocationAccuracy.POOR
    }

    private fun Double.toRadians() = Math.toRadians(this)
}
