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
import com.google.mlkit.vision.face.FaceDetection
import com.google.mlkit.vision.face.FaceDetector
import com.google.mlkit.vision.face.FaceDetectorOptions
import kotlinx.coroutines.tasks.await
import java.io.ByteArrayOutputStream
import java.nio.ByteBuffer
import kotlin.math.sqrt

class FaceDetectionHelper(context: Context) {
    
    private val detector: FaceDetector
    
    init {
        val options = FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
            .setMinFaceSize(0.3f)
            .enableTracking()
            .build()
        
        detector = FaceDetection.getClient(options)
    }
    
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
    
    suspend fun detectFaces(bitmap: Bitmap): List<Face> {
        val inputImage = InputImage.fromBitmap(bitmap, 0)
        return try {
            detector.process(inputImage).await()
        } catch (e: Exception) {
            emptyList()
        }
    }
    
    fun extractFaceEmbedding(face: Face, bitmap: Bitmap): FloatArray {
        // Extract face region from bitmap
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
        
        // Resize to standard size for embedding
        val resized = Bitmap.createScaledBitmap(faceBitmap, 160, 160, true)
        
        // Extract pixel values and normalize
        val pixels = IntArray(160 * 160)
        resized.getPixels(pixels, 0, 160, 0, 0, 160, 160)
        
        // Generate embedding (simplified - in production use a proper face recognition model)
        val embedding = FloatArray(128)
        for (i in 0 until 128) {
            val pixelIndex = (i * pixels.size / 128)
            val pixel = pixels.getOrElse(pixelIndex) { 0 }
            val r = (pixel shr 16) and 0xff
            val g = (pixel shr 8) and 0xff
            val b = pixel and 0xff
            embedding[i] = ((r + g + b) / 3f - 128f) / 128f
        }
        
        // Normalize the embedding
        val norm = sqrt(embedding.map { it * it }.sum())
        if (norm > 0) {
            for (i in embedding.indices) {
                embedding[i] /= norm
            }
        }
        
        faceBitmap.recycle()
        resized.recycle()
        
        return embedding
    }
    
    fun compareFaces(embedding1: FloatArray, embedding2: FloatArray): Double {
        if (embedding1.size != embedding2.size) return 0.0
        
        // Calculate cosine similarity
        var dotProduct = 0.0
        var norm1 = 0.0
        var norm2 = 0.0
        
        for (i in embedding1.indices) {
            dotProduct += embedding1[i] * embedding2[i]
            norm1 += embedding1[i] * embedding1[i]
            norm2 += embedding2[i] * embedding2[i]
        }
        
        val denominator = sqrt(norm1) * sqrt(norm2)
        if (denominator == 0.0) return 0.0
        
        val similarity = dotProduct / denominator
        
        // Convert to percentage (0-100)
        return ((similarity + 1) / 2 * 100).coerceIn(0.0, 100.0)
    }
    
    fun embeddingToString(embedding: FloatArray): String {
        return embedding.joinToString(",") { String.format("%.6f", it) }
    }
    
    fun stringToEmbedding(embeddingString: String): FloatArray {
        return try {
            embeddingString.split(",").map { it.trim().toFloat() }.toFloatArray()
        } catch (e: Exception) {
            FloatArray(128)
        }
    }
    
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
    }
}
