package com.sams.app.utils

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.ImageFormat
import android.graphics.Rect
import android.graphics.YuvImage
import androidx.camera.core.ImageProxy
import com.google.mlkit.vision.common.InputImage
import com.google.mlkit.vision.face.Face
import com.google.mlkit.vision.face.FaceContour
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetector
import com.google.mlkit.vision.face.FaceDetectorOptions
import com.google.mlkit.vision.face.FaceLandmark
import kotlinx.coroutines.tasks.await
import java.io.ByteArrayOutputStream
import kotlin.math.abs
import kotlin.math.sqrt

/**
 * Helper class for face detection and embedding using ML Kit
 * Provides:
 * - Face detection from camera images
 * - Face embedding extraction for storage
 * - Face comparison for verification
 * - Liveness detection hints (blink, head movement)
 */
class FaceDetectionHelper(context: Context) {
    
    private val detector: FaceDetector
    private val faceRecognitionHelper: FaceRecognitionHelper
    
    init {
        val options = FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .setContourMode(FaceDetectorOptions.CONTOUR_MODE_ALL)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
            .setMinFaceSize(0.2f)
            .enableTracking()
            .build()
        
        detector = FaceDetection.getClient(options)
        faceRecognitionHelper = FaceRecognitionHelper(context)
    }
    
    /**
     * Detect faces in an ImageProxy (from CameraX)
     */
    suspend fun detectFaces(imageProxy: ImageProxy): List<Face> {
        val mediaImage = imageProxy.image ?: return emptyList()
        val inputImage = InputImage.fromMediaImage(
            mediaImage,
            imageProxy.imageInfo.rotationDegrees
        )
        
        return try {
            detector.process(inputImage).await()
        } catch (e: Exception) {
            emptyList()
        }
    }
    
    /**
     * Detect faces in a Bitmap
     */
    suspend fun detectFaces(bitmap: Bitmap): List<Face> {
        val inputImage = InputImage.fromBitmap(bitmap, 0)
        return try {
            detector.process(inputImage).await()
        } catch (e: Exception) {
            emptyList()
        }
    }
    
    /**
     * Check liveness indicators from a detected face
     * Returns hints about eye blink and head movement
     */
    fun checkLiveness(face: Face): LivenessResult {
        val leftEyeOpenProb = face.leftEyeOpenProbability ?: 0.5f
        val rightEyeOpenProb = face.rightEyeOpenProbability ?: 0.5f
        val smilingProb = face.smilingProbability ?: 0.0f

        val headEulerAngleY = face.headEulerAngleY // Head rotation left/right
        val headEulerAngleZ = face.headEulerAngleZ // Head tilt
        val headEulerAngleX = face.headEulerAngleX // Head up/down

        // Detect if eyes are blinking (closed) - more lenient threshold
        val isBlinking = leftEyeOpenProb < 0.5f || rightEyeOpenProb < 0.5f

        // Detect head movement (not perfectly still like a photo) - more lenient thresholds
        val hasHeadMovement = kotlin.math.abs(headEulerAngleY) > 3 ||
                              kotlin.math.abs(headEulerAngleZ) > 3 ||
                              kotlin.math.abs(headEulerAngleX) > 3

        // Additional check: if eyes are not fully open (indicates natural variation)
        val hasNaturalEyeVariation = leftEyeOpenProb < 0.9f || rightEyeOpenProb < 0.9f

        return LivenessResult(
            isBlinking = isBlinking,
            hasHeadMovement = hasHeadMovement,
            hasNaturalEyeVariation = hasNaturalEyeVariation,
            leftEyeOpen = leftEyeOpenProb,
            rightEyeOpen = rightEyeOpenProb,
            smiling = smilingProb,
            headRotationY = headEulerAngleY,
            headRotationZ = headEulerAngleZ,
            headRotationX = headEulerAngleX
        ).also {
            android.util.Log.d("FaceDetectionHelper",
                "Liveness check - Blink: $isBlinking, Head movement: $hasHeadMovement, Natural eyes: $hasNaturalEyeVariation, Live: ${it.isProbablyLive()}")
        }
    }
    
    /**
     * Extract face embedding combining:
     * 1. TFLite model (if available) for visual features
     * 2. ML Kit landmarks for geometric features
     * This creates a robust face signature that distinguishes between different people.
     */
    fun extractFaceEmbedding(face: Face, bitmap: Bitmap): FloatArray {
        val boundingBox = face.boundingBox
        
        // Ensure bounds are within the bitmap
        val left = maxOf(0, boundingBox.left)
        val top = maxOf(0, boundingBox.top)
        val width = minOf(boundingBox.width(), bitmap.width - left)
        val height = minOf(boundingBox.height(), bitmap.height - top)
        
        if (width <= 0 || height <= 0) {
            return FloatArray(128)
        }
        
        val faceBitmap = Bitmap.createBitmap(bitmap, left, top, width, height)
        
        // Extract visual embedding (TFLite or fallback)
        val visualEmbedding = faceRecognitionHelper.extractEmbedding(faceBitmap)
        
        // Extract geometric embedding from landmarks
        val geometricEmbedding = extractLandmarkEmbedding(face, width.toFloat(), height.toFloat())
        
        // Combine embeddings: 96 visual + 32 geometric = 128 total
        val combinedEmbedding = FloatArray(128)
        
        // Copy first 96 values from visual embedding
        for (i in 0 until 96) {
            combinedEmbedding[i] = if (i < visualEmbedding.size) visualEmbedding[i] else 0f
        }
        
        // Copy 32 values from geometric embedding
        for (i in 0 until 32) {
            combinedEmbedding[96 + i] = if (i < geometricEmbedding.size) geometricEmbedding[i] else 0f
        }
        
        // Normalize the combined embedding
        val norm = sqrt(combinedEmbedding.map { it * it.toDouble() }.sum()).toFloat()
        if (norm > 0) {
            for (i in combinedEmbedding.indices) {
                combinedEmbedding[i] /= norm
            }
        }
        
        faceBitmap.recycle()
        
        return combinedEmbedding
    }
    
    /**
     * Extract geometric features from face landmarks.
     * These features are unique to each face structure.
     */
    private fun extractLandmarkEmbedding(face: Face, faceWidth: Float, faceHeight: Float): FloatArray {
        val embedding = FloatArray(32)
        var idx = 0
        
        // Get key landmarks
        val leftEye = face.getLandmark(FaceLandmark.LEFT_EYE)
        val rightEye = face.getLandmark(FaceLandmark.RIGHT_EYE)
        val noseBase = face.getLandmark(FaceLandmark.NOSE_BASE)
        val leftMouth = face.getLandmark(FaceLandmark.MOUTH_LEFT)
        val rightMouth = face.getLandmark(FaceLandmark.MOUTH_RIGHT)
        val bottomMouth = face.getLandmark(FaceLandmark.MOUTH_BOTTOM)
        val leftCheek = face.getLandmark(FaceLandmark.LEFT_CHEEK)
        val rightCheek = face.getLandmark(FaceLandmark.RIGHT_CHEEK)
        
        // Calculate normalized distances between landmarks
        if (leftEye != null && rightEye != null) {
            val eyeDistance = distance(leftEye.position.x, leftEye.position.y, 
                                        rightEye.position.x, rightEye.position.y)
            embedding[idx++] = (eyeDistance / faceWidth).toFloat()
            
            // Eye center
            val eyeCenterX = (leftEye.position.x + rightEye.position.x) / 2
            val eyeCenterY = (leftEye.position.y + rightEye.position.y) / 2
            
            if (noseBase != null) {
                val eyeNoseDistance = distance(eyeCenterX, eyeCenterY, 
                                               noseBase.position.x, noseBase.position.y)
                embedding[idx++] = (eyeNoseDistance / faceHeight).toFloat()
            }
            
            if (bottomMouth != null) {
                val eyeMouthDistance = distance(eyeCenterX, eyeCenterY,
                                                bottomMouth.position.x, bottomMouth.position.y)
                embedding[idx++] = (eyeMouthDistance / faceHeight).toFloat()
            }
        }
        
        // Mouth features
        if (leftMouth != null && rightMouth != null) {
            val mouthWidth = distance(leftMouth.position.x, leftMouth.position.y,
                                      rightMouth.position.x, rightMouth.position.y)
            embedding[idx++] = (mouthWidth / faceWidth).toFloat()
            
            if (bottomMouth != null) {
                val mouthHeight = abs(bottomMouth.position.y - leftMouth.position.y)
                embedding[idx++] = (mouthHeight / faceHeight).toFloat()
            }
        }
        
        // Nose features
        if (noseBase != null && bottomMouth != null) {
            val noseToMouth = distance(noseBase.position.x, noseBase.position.y,
                                       bottomMouth.position.x, bottomMouth.position.y)
            embedding[idx++] = (noseToMouth / faceHeight).toFloat()
        }
        
        // Cheek features
        if (leftCheek != null && rightCheek != null && noseBase != null) {
            val leftCheekToNose = distance(leftCheek.position.x, leftCheek.position.y,
                                           noseBase.position.x, noseBase.position.y)
            val rightCheekToNose = distance(rightCheek.position.x, rightCheek.position.y,
                                            noseBase.position.x, noseBase.position.y)
            embedding[idx++] = (leftCheekToNose / faceWidth).toFloat()
            embedding[idx++] = (rightCheekToNose / faceWidth).toFloat()
            embedding[idx++] = ((leftCheekToNose - rightCheekToNose) / faceWidth).toFloat()
        }
        
        // Face pose angles (unique to face structure)
        embedding[idx++] = ((face.headEulerAngleX + 90) / 180f)
        embedding[idx++] = ((face.headEulerAngleY + 90) / 180f)
        embedding[idx++] = ((face.headEulerAngleZ + 90) / 180f)
        
        // Face contour features
        val faceContour = face.getContour(FaceContour.FACE)
        if (faceContour != null) {
            val points = faceContour.points
            if (points.isNotEmpty()) {
                // Calculate face shape ratios
                val minX = points.minOf { it.x }
                val maxX = points.maxOf { it.x }
                val minY = points.minOf { it.y }
                val maxY = points.maxOf { it.y }
                val contourWidth = maxX - minX
                val contourHeight = maxY - minY
                
                if (contourHeight > 0) {
                    embedding[idx++] = (contourWidth / contourHeight).toFloat()
                }
                
                // Sample contour points for shape signature
                val sampleIndices = listOf(0, points.size / 4, points.size / 2, 3 * points.size / 4)
                for (sampleIdx in sampleIndices) {
                    val point = points.getOrNull(sampleIdx) ?: continue
                    if (idx < 32 && contourWidth > 0) {
                        embedding[idx++] = ((point.x - minX) / contourWidth).toFloat()
                    }
                    if (idx < 32 && contourHeight > 0) {
                        embedding[idx++] = ((point.y - minY) / contourHeight).toFloat()
                    }
                }
            }
        }
        
        // Eye contours for eye shape
        val leftEyeContour = face.getContour(FaceContour.LEFT_EYE)
        val rightEyeContour = face.getContour(FaceContour.RIGHT_EYE)
        
        if (leftEyeContour != null && rightEyeContour != null) {
            val leftPoints = leftEyeContour.points
            val rightPoints = rightEyeContour.points
            
            if (leftPoints.isNotEmpty() && rightPoints.isNotEmpty()) {
                // Eye aspect ratios
                val leftWidth = leftPoints.maxOf { it.x } - leftPoints.minOf { it.x }
                val leftHeight = leftPoints.maxOf { it.y } - leftPoints.minOf { it.y }
                val rightWidth = rightPoints.maxOf { it.x } - rightPoints.minOf { it.x }
                val rightHeight = rightPoints.maxOf { it.y } - rightPoints.minOf { it.y }
                
                if (idx < 32 && leftHeight > 0) embedding[idx++] = (leftWidth / leftHeight).toFloat()
                if (idx < 32 && rightHeight > 0) embedding[idx++] = (rightWidth / rightHeight).toFloat()
            }
        }
        
        // Normalize the geometric embedding
        val norm = sqrt(embedding.take(idx).map { it * it.toDouble() }.sum()).toFloat()
        if (norm > 0) {
            for (i in 0 until idx) {
                embedding[i] /= norm
            }
        }
        
        return embedding
    }
    
    private fun distance(x1: Float, y1: Float, x2: Float, y2: Float): Double {
        val dx = (x1 - x2).toDouble()
        val dy = (y1 - y2).toDouble()
        return sqrt(dx * dx + dy * dy)
    }
    
    /**
     * Compare two face embeddings and return similarity percentage
     * Uses cosine similarity for better face matching
     * For face verification (1:1), we use a more realistic confidence mapping
     */
    fun compareFaces(embedding1: FloatArray, embedding2: FloatArray): Double {
        if (embedding1.size != embedding2.size) return 0.0

        // Check for zero embeddings (invalid)
        val norm1 = sqrt(embedding1.map { it * it.toDouble() }.sum())
        val norm2 = sqrt(embedding2.map { it * it.toDouble() }.sum())
        if (norm1 < 0.1 || norm2 < 0.1) return 0.0

        // Calculate cosine similarity
        var dotProduct = 0.0
        for (i in embedding1.indices) {
            dotProduct += embedding1[i] * embedding2[i]
        }

        val cosineSimilarity = dotProduct / (norm1 * norm2)

        // More realistic confidence mapping for face recognition
        // Based on typical face recognition system performance
        val percentage = when {
            cosineSimilarity >= 0.9 -> 90 + (cosineSimilarity - 0.9) * 100   // 0.9-1.0 -> 90-100%
            cosineSimilarity >= 0.8 -> 70 + (cosineSimilarity - 0.8) * 200   // 0.8-0.9 -> 70-90%
            cosineSimilarity >= 0.7 -> 40 + (cosineSimilarity - 0.7) * 300   // 0.7-0.8 -> 40-70%
            cosineSimilarity >= 0.6 -> 10 + (cosineSimilarity - 0.6) * 300   // 0.6-0.7 -> 10-40%
            cosineSimilarity >= 0.4 -> 1 + (cosineSimilarity - 0.4) * 22.5   // 0.4-0.6 -> 1-10%
            else -> 0.0  // Below 0.4 -> 0%
        }

        // Log for debugging
        android.util.Log.d("FaceDetectionHelper", "Cosine similarity: $cosineSimilarity, Confidence: $percentage%")

        return percentage.coerceIn(0.0, 100.0)
    }
    
    /**
     * Convert embedding to string for API transmission
     */
    fun embeddingToString(embedding: FloatArray): String {
        return embedding.joinToString(",") { String.format("%.6f", it) }
    }
    
    /**
     * Parse embedding string back to FloatArray
     */
    fun stringToEmbedding(embeddingString: String): FloatArray {
        return try {
            embeddingString.split(",").map { it.trim().toFloat() }.toFloatArray()
        } catch (e: Exception) {
            FloatArray(128)
        }
    }
    
    /**
     * Convert ImageProxy to Bitmap
     */
    fun imageProxyToBitmap(imageProxy: ImageProxy): Bitmap? {
        val planes = imageProxy.planes
        val yBuffer = planes[0].buffer
        val uBuffer = planes[1].buffer
        val vBuffer = planes[2].buffer
        
        val ySize = yBuffer.remaining()
        val uSize = uBuffer.remaining()
        val vSize = vBuffer.remaining()
        
        val nv21 = ByteArray(ySize + uSize + vSize)
        
        yBuffer.get(nv21, 0, ySize)
        vBuffer.get(nv21, ySize, vSize)
        uBuffer.get(nv21, ySize + vSize, uSize)
        
        val yuvImage = YuvImage(nv21, ImageFormat.NV21, imageProxy.width, imageProxy.height, null)
        val out = ByteArrayOutputStream()
        yuvImage.compressToJpeg(Rect(0, 0, imageProxy.width, imageProxy.height), 100, out)
        val imageBytes = out.toByteArray()
        
        return BitmapFactory.decodeByteArray(imageBytes, 0, imageBytes.size)
    }
    
    fun close() {
        detector.close()
        faceRecognitionHelper.close()
    }
}

/**
 * Result of liveness detection check
 */
data class LivenessResult(
    val isBlinking: Boolean,
    val hasHeadMovement: Boolean,
    val hasNaturalEyeVariation: Boolean,
    val leftEyeOpen: Float,
    val rightEyeOpen: Float,
    val smiling: Float,
    val headRotationY: Float,
    val headRotationZ: Float,
    val headRotationX: Float
) {
    /**
     * More lenient liveness check - face shows signs of being a real person
     * Accepts faces that show natural variation (blinking, head movement, or non-perfect eye openness)
     */
    fun isProbablyLive(): Boolean = isBlinking || hasHeadMovement || hasNaturalEyeVariation
}
