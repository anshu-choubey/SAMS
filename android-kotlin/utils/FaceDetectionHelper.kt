package com.sams.app.utils

import android.graphics.Bitmap
import android.graphics.Rect
import android.util.Base64
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.Face
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetector
import com.google.mlkit.vision.face.FaceDetectorOptions
import kotlinx.coroutines.suspendCancellableCoroutine
import java.io.ByteArrayOutputStream
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlin.math.sqrt

/**
 * ML Kit Face Detection Helper
 * Handles face detection, embedding extraction, and verification
 */
class FaceDetectionHelper {
    
    companion object {
        const val FACE_CONFIDENCE_THRESHOLD = 85.0
        const val MIN_FACE_SIZE = 0.15f
        const val EMBEDDING_SIZE = 128
    }
    
    private val detector: FaceDetector
    
    init {
        val options = FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
            .setContourMode(FaceDetectorOptions.CONTOUR_MODE_ALL)
            .setMinFaceSize(MIN_FACE_SIZE)
            .enableTracking()
            .build()
        
        detector = FaceDetection.getClient(options)
    }
    
    /**
     * Detect faces in an image
     */
    suspend fun detectFaces(bitmap: Bitmap): List<Face> = suspendCancellableCoroutine { cont ->
        val image = InputImage.fromBitmap(bitmap, 0)
        
        detector.process(image)
            .addOnSuccessListener { faces ->
                cont.resume(faces)
            }
            .addOnFailureListener { e ->
                cont.resumeWithException(e)
            }
    }
    
    /**
     * Check if a single valid face is present
     */
    suspend fun validateSingleFace(bitmap: Bitmap): FaceValidationResult {
        val faces = detectFaces(bitmap)
        
        return when {
            faces.isEmpty() -> FaceValidationResult.NoFaceDetected
            faces.size > 1 -> FaceValidationResult.MultipleFacesDetected
            else -> {
                val face = faces[0]
                
                // Check face angle (should be mostly frontal)
                val headEulerAngleY = face.headEulerAngleY // Left-right rotation
                val headEulerAngleZ = face.headEulerAngleZ // Tilt
                
                if (kotlin.math.abs(headEulerAngleY) > 30 || kotlin.math.abs(headEulerAngleZ) > 20) {
                    FaceValidationResult.FaceNotFrontal(headEulerAngleY, headEulerAngleZ)
                } else {
                    FaceValidationResult.Success(face)
                }
            }
        }
    }
    
    /**
     * Extract face embedding from bitmap
     * Creates a simplified embedding based on face landmarks and contours
     */
    suspend fun extractFaceEmbedding(bitmap: Bitmap): FaceEmbeddingResult {
        val validationResult = validateSingleFace(bitmap)
        
        if (validationResult !is FaceValidationResult.Success) {
            return FaceEmbeddingResult.ValidationFailed(validationResult)
        }
        
        val face = validationResult.face
        
        // Extract face region
        val faceBitmap = cropFace(bitmap, face.boundingBox)
        
        // Create embedding from face features
        val embedding = createEmbedding(face, faceBitmap)
        
        return FaceEmbeddingResult.Success(embedding, face)
    }
    
    /**
     * Create face embedding from detected face
     * In production, you would use a dedicated face embedding model (FaceNet, ArcFace, etc.)
     */
    private fun createEmbedding(face: Face, faceBitmap: Bitmap): FloatArray {
        val embedding = FloatArray(EMBEDDING_SIZE)
        var index = 0
        
        // Add head rotation angles (normalized)
        embedding[index++] = face.headEulerAngleX / 90f
        embedding[index++] = face.headEulerAngleY / 90f
        embedding[index++] = face.headEulerAngleZ / 90f
        
        // Add face classifications
        face.smilingProbability?.let { embedding[index++] = it }
        face.leftEyeOpenProbability?.let { embedding[index++] = it }
        face.rightEyeOpenProbability?.let { embedding[index++] = it }
        
        // Add landmark positions (normalized to face bounding box)
        val boundingBox = face.boundingBox
        val width = boundingBox.width().toFloat()
        val height = boundingBox.height().toFloat()
        
        face.allLandmarks.forEach { landmark ->
            if (index < EMBEDDING_SIZE - 1) {
                embedding[index++] = (landmark.position.x - boundingBox.left) / width
                embedding[index++] = (landmark.position.y - boundingBox.top) / height
            }
        }
        
        // Add contour points (sampled)
        face.allContours.forEach { contour ->
            contour.points.forEachIndexed { i, point ->
                if (i % 3 == 0 && index < EMBEDDING_SIZE - 1) {
                    embedding[index++] = (point.x - boundingBox.left) / width
                    embedding[index++] = (point.y - boundingBox.top) / height
                }
            }
        }
        
        // Fill remaining with pixel features
        val scaledBitmap = Bitmap.createScaledBitmap(faceBitmap, 16, 16, true)
        val pixels = IntArray(256)
        scaledBitmap.getPixels(pixels, 0, 16, 0, 0, 16, 16)
        
        pixels.forEachIndexed { i, pixel ->
            if (index < EMBEDDING_SIZE) {
                // Grayscale conversion normalized
                val gray = (0.299 * ((pixel shr 16) and 0xFF) +
                           0.587 * ((pixel shr 8) and 0xFF) +
                           0.114 * (pixel and 0xFF)) / 255f
                embedding[index++] = gray.toFloat()
            }
        }
        
        // Normalize embedding
        return normalizeEmbedding(embedding)
    }
    
    /**
     * Normalize embedding to unit vector
     */
    private fun normalizeEmbedding(embedding: FloatArray): FloatArray {
        var sum = 0f
        embedding.forEach { sum += it * it }
        val magnitude = sqrt(sum)
        
        return if (magnitude > 0) {
            embedding.map { it / magnitude }.toFloatArray()
        } else {
            embedding
        }
    }
    
    /**
     * Calculate cosine similarity between two embeddings
     */
    fun calculateSimilarity(embedding1: FloatArray, embedding2: FloatArray): Float {
        if (embedding1.size != embedding2.size) return 0f
        
        var dotProduct = 0f
        var norm1 = 0f
        var norm2 = 0f
        
        for (i in embedding1.indices) {
            dotProduct += embedding1[i] * embedding2[i]
            norm1 += embedding1[i] * embedding1[i]
            norm2 += embedding2[i] * embedding2[i]
        }
        
        val magnitude = sqrt(norm1) * sqrt(norm2)
        return if (magnitude > 0) dotProduct / magnitude else 0f
    }
    
    /**
     * Verify face against stored embedding
     * Returns confidence score 0-100
     */
    fun verifyFace(currentEmbedding: FloatArray, storedEmbedding: FloatArray): Float {
        val similarity = calculateSimilarity(currentEmbedding, storedEmbedding)
        // Convert similarity (-1 to 1) to confidence (0 to 100)
        return ((similarity + 1) / 2 * 100).coerceIn(0f, 100f)
    }
    
    /**
     * Crop face region from bitmap
     */
    private fun cropFace(bitmap: Bitmap, boundingBox: Rect): Bitmap {
        // Add padding around face
        val padding = (boundingBox.width() * 0.2).toInt()
        val left = maxOf(0, boundingBox.left - padding)
        val top = maxOf(0, boundingBox.top - padding)
        val right = minOf(bitmap.width, boundingBox.right + padding)
        val bottom = minOf(bitmap.height, boundingBox.bottom + padding)
        
        return Bitmap.createBitmap(bitmap, left, top, right - left, bottom - top)
    }
    
    /**
     * Encode embedding to Base64 string for API transmission
     */
    fun encodeEmbedding(embedding: FloatArray): String {
        val byteArray = ByteArray(embedding.size * 4)
        embedding.forEachIndexed { index, value ->
            val bits = java.lang.Float.floatToIntBits(value)
            byteArray[index * 4] = (bits shr 24).toByte()
            byteArray[index * 4 + 1] = (bits shr 16).toByte()
            byteArray[index * 4 + 2] = (bits shr 8).toByte()
            byteArray[index * 4 + 3] = bits.toByte()
        }
        return Base64.encodeToString(byteArray, Base64.NO_WRAP)
    }
    
    /**
     * Decode Base64 string to embedding
     */
    fun decodeEmbedding(encoded: String): FloatArray {
        val byteArray = Base64.decode(encoded, Base64.NO_WRAP)
        val embedding = FloatArray(byteArray.size / 4)
        
        for (i in embedding.indices) {
            val bits = ((byteArray[i * 4].toInt() and 0xFF) shl 24) or
                       ((byteArray[i * 4 + 1].toInt() and 0xFF) shl 16) or
                       ((byteArray[i * 4 + 2].toInt() and 0xFF) shl 8) or
                       (byteArray[i * 4 + 3].toInt() and 0xFF)
            embedding[i] = java.lang.Float.intBitsToFloat(bits)
        }
        
        return embedding
    }
    
    /**
     * Convert bitmap to Base64 for optional storage
     */
    fun bitmapToBase64(bitmap: Bitmap, quality: Int = 80): String {
        val outputStream = ByteArrayOutputStream()
        bitmap.compress(Bitmap.CompressFormat.JPEG, quality, outputStream)
        return Base64.encodeToString(outputStream.toByteArray(), Base64.NO_WRAP)
    }
    
    /**
     * Perform liveness detection (basic implementation)
     */
    suspend fun checkLiveness(frames: List<Bitmap>): LivenessResult {
        if (frames.size < 3) {
            return LivenessResult.InsufficientFrames
        }
        
        val eyeOpenProbabilities = mutableListOf<Float>()
        
        frames.forEach { frame ->
            val faces = detectFaces(frame)
            if (faces.isNotEmpty()) {
                faces[0].leftEyeOpenProbability?.let { eyeOpenProbabilities.add(it) }
            }
        }
        
        if (eyeOpenProbabilities.size < 3) {
            return LivenessResult.FaceNotConsistent
        }
        
        // Check for blink (eye open probability variation)
        val hasVariation = eyeOpenProbabilities.any { it < 0.3f }
        
        return if (hasVariation) {
            LivenessResult.LivenessConfirmed
        } else {
            LivenessResult.NoBlinkDetected
        }
    }
    
    fun close() {
        detector.close()
    }
}

// ==================== Result Classes ====================

sealed class FaceValidationResult {
    data class Success(val face: Face) : FaceValidationResult()
    object NoFaceDetected : FaceValidationResult()
    object MultipleFacesDetected : FaceValidationResult()
    data class FaceNotFrontal(val yAngle: Float, val zAngle: Float) : FaceValidationResult()
}

sealed class FaceEmbeddingResult {
    data class Success(val embedding: FloatArray, val face: Face) : FaceEmbeddingResult()
    data class ValidationFailed(val reason: FaceValidationResult) : FaceEmbeddingResult()
}

sealed class LivenessResult {
    object LivenessConfirmed : LivenessResult()
    object NoBlinkDetected : LivenessResult()
    object InsufficientFrames : LivenessResult()
    object FaceNotConsistent : LivenessResult()
}
