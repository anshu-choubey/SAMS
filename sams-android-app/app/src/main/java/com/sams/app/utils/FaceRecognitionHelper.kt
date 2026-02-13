package com.sams.app.utils

import android.content.Context
import android.graphics.Bitmap
import org.tensorflow.lite.Interpreter
import org.tensorflow.lite.support.common.FileUtil
import java.nio.ByteBuffer
import java.nio.ByteOrder
import kotlin.math.sqrt

/**
 * TensorFlow Lite based face recognition helper.
 * Uses MobileFaceNet model for real face embeddings.
 */
class FaceRecognitionHelper(private val context: Context) {
    
    private var interpreter: Interpreter? = null
    private var isModelLoaded = false
    
    companion object {
        private const val MODEL_FILE = "mobilefacenet.tflite"
        private const val INPUT_SIZE = 112 // MobileFaceNet input size
        private const val EMBEDDING_SIZE = 128
        private const val PIXEL_SIZE = 3 // RGB
    }
    
    init {
        try {
            loadModel()
        } catch (e: Exception) {
            android.util.Log.e("FaceRecognitionHelper", "Failed to load model: ${e.message}")
        }
    }
    
    private fun loadModel() {
        try {
            val modelBuffer = FileUtil.loadMappedFile(context, MODEL_FILE)
            val options = Interpreter.Options().apply {
                setNumThreads(4)
            }
            interpreter = Interpreter(modelBuffer, options)
            isModelLoaded = true
            android.util.Log.d("FaceRecognitionHelper", "Model loaded successfully")
        } catch (e: Exception) {
            android.util.Log.e("FaceRecognitionHelper", "Model not found: ${e.message}")
            isModelLoaded = false
        }
    }
    
    fun isReady(): Boolean = isModelLoaded && interpreter != null
    
    /**
     * Extract face embedding using TensorFlow Lite MobileFaceNet model.
     * This produces a 128-dimensional vector that uniquely identifies a face.
     */
    fun extractEmbedding(faceBitmap: Bitmap): FloatArray {
        if (!isReady()) {
            android.util.Log.w("FaceRecognitionHelper", "Model not ready, using fallback")
            return extractFallbackEmbedding(faceBitmap)
        }
        
        // Resize face to model input size
        val resizedBitmap = Bitmap.createScaledBitmap(faceBitmap, INPUT_SIZE, INPUT_SIZE, true)
        
        // Prepare input buffer
        val inputBuffer = ByteBuffer.allocateDirect(1 * INPUT_SIZE * INPUT_SIZE * PIXEL_SIZE * 4)
        inputBuffer.order(ByteOrder.nativeOrder())
        
        // Normalize pixels: (pixel - 127.5) / 128.0
        val pixels = IntArray(INPUT_SIZE * INPUT_SIZE)
        resizedBitmap.getPixels(pixels, 0, INPUT_SIZE, 0, 0, INPUT_SIZE, INPUT_SIZE)
        
        for (pixel in pixels) {
            val r = ((pixel shr 16) and 0xFF)
            val g = ((pixel shr 8) and 0xFF)
            val b = (pixel and 0xFF)
            
            // MobileFaceNet normalization
            inputBuffer.putFloat((r - 127.5f) / 128.0f)
            inputBuffer.putFloat((g - 127.5f) / 128.0f)
            inputBuffer.putFloat((b - 127.5f) / 128.0f)
        }
        
        // Prepare output buffer
        val outputBuffer = Array(1) { FloatArray(EMBEDDING_SIZE) }
        
        // Run inference
        try {
            interpreter?.run(inputBuffer, outputBuffer)
        } catch (e: Exception) {
            android.util.Log.e("FaceRecognitionHelper", "Inference failed: ${e.message}")
            resizedBitmap.recycle()
            return extractFallbackEmbedding(faceBitmap)
        }
        
        resizedBitmap.recycle()
        
        // Normalize embedding to unit vector
        val embedding = outputBuffer[0]
        val norm = sqrt(embedding.map { it * it.toDouble() }.sum()).toFloat()
        if (norm > 0) {
            for (i in embedding.indices) {
                embedding[i] /= norm
            }
        }
        
        return embedding
    }
    
    /**
     * Fallback embedding extraction when TFLite model is not available.
     * Uses a more sophisticated approach than simple pixel sampling.
     */
    private fun extractFallbackEmbedding(faceBitmap: Bitmap): FloatArray {
        val resized = Bitmap.createScaledBitmap(faceBitmap, 64, 64, true)
        val pixels = IntArray(64 * 64)
        resized.getPixels(pixels, 0, 64, 0, 0, 64, 64)
        
        val embedding = FloatArray(128)
        
        // Extract features from different regions of the face
        // This creates a more discriminative embedding than simple pixel sampling
        
        // Divide face into 8x8 grid = 64 regions
        val regionSize = 8
        var embeddingIdx = 0
        
        for (gridY in 0 until 8) {
            for (gridX in 0 until 8) {
                if (embeddingIdx >= 128) break
                
                var sumR = 0.0
                var sumG = 0.0
                var sumB = 0.0
                var count = 0
                
                // Calculate mean color for this region
                for (y in gridY * regionSize until (gridY + 1) * regionSize) {
                    for (x in gridX * regionSize until (gridX + 1) * regionSize) {
                        val idx = y * 64 + x
                        if (idx < pixels.size) {
                            val pixel = pixels[idx]
                            sumR += ((pixel shr 16) and 0xFF)
                            sumG += ((pixel shr 8) and 0xFF)
                            sumB += (pixel and 0xFF)
                            count++
                        }
                    }
                }
                
                if (count > 0) {
                    val luminance = (sumR * 0.299 + sumG * 0.587 + sumB * 0.114) / count
                    // First 64 values: luminance features
                    embedding[embeddingIdx] = ((luminance - 128.0) / 128.0).toFloat()
                    
                    // Next 64 values: texture features (variance)
                    if (embeddingIdx + 64 < 128) {
                        var variance = 0.0
                        val meanLum = luminance
                        for (y in gridY * regionSize until (gridY + 1) * regionSize) {
                            for (x in gridX * regionSize until (gridX + 1) * regionSize) {
                                val idx = y * 64 + x
                                if (idx < pixels.size) {
                                    val pixel = pixels[idx]
                                    val r = ((pixel shr 16) and 0xFF)
                                    val g = ((pixel shr 8) and 0xFF)
                                    val b = (pixel and 0xFF)
                                    val lum = r * 0.299 + g * 0.587 + b * 0.114
                                    variance += (lum - meanLum) * (lum - meanLum)
                                }
                            }
                        }
                        variance /= count
                        embedding[embeddingIdx + 64] = (sqrt(variance) / 128.0).toFloat()
                    }
                }
                embeddingIdx++
            }
        }
        
        // Normalize embedding
        val norm = sqrt(embedding.map { it * it.toDouble() }.sum()).toFloat()
        if (norm > 0) {
            for (i in embedding.indices) {
                embedding[i] /= norm
            }
        }
        
        resized.recycle()
        return embedding
    }
    
    /**
     * Compare two face embeddings using cosine similarity.
     * Returns similarity score as percentage (0-100).
     */
    fun compare(embedding1: FloatArray, embedding2: FloatArray): Double {
        if (embedding1.size != embedding2.size) return 0.0
        
        // Cosine similarity for TFLite embeddings
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
        
        // Convert cosine similarity (-1 to 1) to percentage (0 to 100)
        // For face recognition, we typically want a stricter threshold
        // Cosine similarity of 0.5+ typically indicates same person
        return ((similarity + 1) / 2 * 100).coerceIn(0.0, 100.0)
    }
    
    fun close() {
        interpreter?.close()
        interpreter = null
        isModelLoaded = false
    }
}
