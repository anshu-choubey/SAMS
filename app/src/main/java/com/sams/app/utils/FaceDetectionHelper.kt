package com.sams.app.utils

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Color
import android.graphics.ImageFormat
import android.graphics.Matrix
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
import com.google.mlkit.vision.facemesh.FaceMesh
import com.google.mlkit.vision.facemesh.FaceMeshDetection
import com.google.mlkit.vision.facemesh.FaceMeshDetector
import com.google.mlkit.vision.facemesh.FaceMeshDetectorOptions
import kotlinx.coroutines.tasks.await
import org.tensorflow.lite.Interpreter
import org.tensorflow.lite.support.common.FileUtil
import timber.log.Timber
import java.io.ByteArrayOutputStream
import java.nio.ByteBuffer
import java.nio.ByteOrder
import kotlin.math.abs
import kotlin.math.sqrt
import androidx.core.graphics.scale

class FaceDetectionHelper(private val context: Context) {

    companion object {
        private const val TAG = "FaceDetectionHelper"

        private const val MODEL_INPUT_SIZE = 160
        private const val EMBEDDING_SIZE = 128
        private const val IMAGE_MEAN = 128.0f
        private const val IMAGE_STD = 128.0f
        private const val FACE_PAD_RATIO = 1.3f
        private const val MIN_FACE_DIMENSION = 20

        private const val BLINK_CLOSE_THRESHOLD = 0.22f
        private const val BLINK_OPEN_THRESHOLD = 0.55f
        private const val HEAD_TURN_THRESHOLD = 20f
        private const val HEAD_CENTER_THRESHOLD = 8f
        private const val FACE_AREA_MIN = 12000
        private const val FACE_AREA_MAX = 600000
        private const val CHALLENGE_TIMEOUT_MS = 15000L

        private const val REQUIRED_MATCH_FRAMES = 5
        private const val MAX_CONFIDENCE_JUMP = 12.0
        private const val MIN_IDENTITY_MATCH = 60.0
        private const val MAX_IDENTITY_FAILS = 2

        // Anti-spoofing thresholds
        private const val DEPTH_VARIANCE_THRESHOLD = 1.0f
        private const val TEXTURE_SHARPNESS_MIN = 12.0
        private const val TEXTURE_SHARPNESS_MAX = 900.0
        private const val MOTION_HISTORY_SIZE = 12
        private const val MIN_MOTION_VARIANCE = 0.2f
        private const val SPOOF_CHECK_INTERVAL = 1
        private const val SCREEN_BLUE_RATIO_MAX = 0.42f
        private const val MIN_SPOOF_PASS_STREAK = 5

        // Embedding consistency
        private const val EMBEDDING_HISTORY_SIZE = 8
        private const val MIN_EMBEDDING_CONSISTENCY = 0.55
        private const val PRE_LIVENESS_IDENTITY_FRAMES = 3
    }

    private val detector: FaceDetector = FaceDetection.getClient(
        FaceDetectorOptions.Builder()
            .setPerformanceMode(FaceDetectorOptions.PERFORMANCE_MODE_ACCURATE)
            .setLandmarkMode(FaceDetectorOptions.LANDMARK_MODE_ALL)
            .setContourMode(FaceDetectorOptions.CONTOUR_MODE_ALL)
            .setClassificationMode(FaceDetectorOptions.CLASSIFICATION_MODE_ALL)
            .setMinFaceSize(0.2f)
            .enableTracking()
            .build()
    )

    private val meshDetector: FaceMeshDetector = FaceMeshDetection.getClient(
        FaceMeshDetectorOptions.Builder()
            .setUseCase(FaceMeshDetectorOptions.FACE_MESH)
            .build()
    )

    private val tfliteInterpreter: Interpreter by lazy {
        val modelBuffer = FileUtil.loadMappedFile(context, "facenet.tflite")
        val options = Interpreter.Options().apply { numThreads = 4 }
        Interpreter(modelBuffer, options)
    }

    // ── Liveness State ──────────────────────────────────────────────────────────

    enum class Challenge { BLINK, TURN_LEFT, TURN_RIGHT }
    enum class BlinkPhase { WAITING_CLOSE, WAITING_OPEN, DONE }

    data class LivenessState(
        val challenges: List<Challenge>,
        val currentIndex: Int = 0,
        val startTime: Long = System.currentTimeMillis(),
        val blinkPhase: BlinkPhase = BlinkPhase.WAITING_CLOSE,
        val blinkCloseTime: Long = 0L,
        val turnDetected: Boolean = false,
        val allPassed: Boolean = false,
        val identityFailCount: Int = 0
    ) {
        val currentChallenge: Challenge? get() =
            if (currentIndex < challenges.size) challenges[currentIndex] else null
        val progress: Float get() =
            if (challenges.isEmpty()) 1f else currentIndex.toFloat() / challenges.size
    }

    private var livenessState: LivenessState? = null
    private var postLivenessMatchCount = 0
    private var lastMatchConfidence: Double? = null
    private var identityFailStreak = 0

    // Anti-spoofing state
    private var frameCount = 0
    private var spoofDetected = false
    private var spoofReason = ""
    private val motionHistory = ArrayDeque<Pair<Float, Float>>(MOTION_HISTORY_SIZE + 1)
    private var lastDepthVariance = 0f
    private var depthPassCount = 0
    private val depthHistory = ArrayDeque<Float>(10)
    private var spoofPassStreak = 0
    private var totalSpoofPasses = 0

    // Embedding consistency tracking — detect face swaps between frames
    private val recentEmbeddings = ArrayDeque<FloatArray>(EMBEDDING_HISTORY_SIZE + 1)
    private var preLivenessIdentityCount = 0
    private var identityLocked = false

    fun startLivenessChallenge() {
        val selected = listOf(Challenge.BLINK, Challenge.TURN_LEFT, Challenge.TURN_RIGHT)
            .shuffled().take(2)
        livenessState = LivenessState(challenges = selected)
        postLivenessMatchCount = 0
        lastMatchConfidence = 0.0
        frameCount = 0
        spoofDetected = false
        spoofReason = ""
        motionHistory.clear()
        depthHistory.clear()
        depthPassCount = 0
        spoofPassStreak = 0
        totalSpoofPasses = 0
        recentEmbeddings.clear()
        preLivenessIdentityCount = 0
        identityLocked = false
        Timber.tag(TAG).d("Liveness challenges: $selected")
    }

    fun getLivenessInstruction(): String {
        val state = livenessState ?: return "Initializing..."
        if (state.allPassed) return "Hold steady..."
        val ch = state.currentChallenge ?: return "Hold steady..."
        return when (ch) {
            Challenge.BLINK -> "Blink your eyes naturally"
            Challenge.TURN_LEFT -> "Slowly turn head left"
            Challenge.TURN_RIGHT -> "Slowly turn head right"
        }
    }

    fun getLivenessProgress(): Float = livenessState?.progress ?: 0f
    fun isLivenessPassed(): Boolean = livenessState?.allPassed == true
    fun getChallengeCount(): Int = livenessState?.challenges?.size ?: 0
    fun getCompletedCount(): Int = livenessState?.currentIndex ?: 0

    // ── Face Detection ──────────────────────────────────────────────────────────

    suspend fun detectFaces(bitmap: Bitmap?): List<Face> {
        if (bitmap == null) return emptyList()
        return try {
            detector.process(InputImage.fromBitmap(bitmap, 0)).await()
        } catch (e: Exception) {
            Timber.tag(TAG).e("detectFaces error: ${e.message}")
            emptyList()
        }
    }

    // ── 3D Mesh Anti-Spoofing ───────────────────────────────────────────────────

    data class SpoofResult(val isReal: Boolean, val confidence: Float, val reason: String)

    private suspend fun checkAntiSpoofing(bitmap: Bitmap, face: Face): SpoofResult {
        val checks = mutableListOf<Pair<String, Boolean>>()
        var overallScore = 0f

        // Check 1: 3D Depth analysis via Face Mesh
        try {
            val meshes = meshDetector.process(InputImage.fromBitmap(bitmap, 0)).await()
            if (meshes.isNotEmpty()) {
                val mesh = meshes[0]
                val depthResult = analyzeDepth(mesh)
                checks.add("depth" to depthResult.first)
                if (depthResult.first) overallScore += 25f
                lastDepthVariance = depthResult.second
                depthHistory.addLast(depthResult.second)
                if (depthHistory.size > 8) depthHistory.removeFirst()
                if (depthResult.first) depthPassCount++
            }
        } catch (e: Exception) {
            Timber.tag(TAG).w("Mesh detection error: ${e.message}")
        }

        // Check 2: Texture sharpness (detect photo/screen artifacts)
        val textureResult = analyzeTexture(face, bitmap)
        checks.add("texture" to textureResult.first)
        if (textureResult.first) overallScore += 20f

        // Check 3: Screen color detection (screens emit blue-heavy light)
        val screenResult = detectScreenColors(face, bitmap)
        checks.add("screen" to !screenResult.first)
        if (!screenResult.first) overallScore += 25f

        // Check 4: Micro-motion analysis
        val box = face.boundingBox
        val centerX = (box.left + box.right) / 2f
        val centerY = (box.top + box.bottom) / 2f
        motionHistory.addLast(centerX to centerY)
        if (motionHistory.size > MOTION_HISTORY_SIZE) motionHistory.removeFirst()
        val motionResult = analyzeMotion()
        checks.add("motion" to motionResult.first)
        if (motionResult.first) overallScore += 15f

        // Check 5: Eye reflection / naturalness
        val eyeResult = analyzeEyeNaturalness(face)
        checks.add("eyes" to eyeResult)
        if (eyeResult) overallScore += 15f

        val failedChecks = checks.filter { !it.second }.map { it.first }

        // Depth is MANDATORY after enough frames to collect data (frame 4+)
        val depthPassed = checks.find { it.first == "depth" }?.second ?: false
        val screenPassed = checks.find { it.first == "screen" }?.second ?: true

        val passed = when {
            frameCount > 4 && !depthPassed -> false
            !screenPassed -> false
            else -> overallScore >= 55f
        }

        val reason = if (passed) "Real face" else "Spoof: ${failedChecks.joinToString(", ")}"

        return SpoofResult(passed, overallScore, reason)
    }

    /**
     * Analyze z-depth variance from 468 3D face mesh landmarks.
     * Real faces have significant depth variation (nose protrudes, ears recede).
     * Photos are flat — z-values are nearly uniform.
     */
    private fun analyzeDepth(mesh: FaceMesh): Pair<Boolean, Float> {
        val points = mesh.allPoints
        if (points.size < 100) return false to 0f

        val zValues = points.map { it.position.z }
        val mean = zValues.average().toFloat()
        val variance = zValues.map { (it - mean) * (it - mean) }.average().toFloat()
        val stdDev = sqrt(variance.toDouble()).toFloat()

        // Nose tip region (indices ~1-4) vs ear region (indices ~234, ~454)
        val noseTipZ = points.filter { it.index in listOf(1, 2, 3, 4, 5, 6) }
            .map { it.position.z }.average().toFloat()
        val sideZ = points.filter { it.index in listOf(234, 454, 127, 356, 93, 323) }
            .map { it.position.z }.average().toFloat()
        val noseToSideDepth = abs(noseTipZ - sideZ)

        Timber.tag(TAG).d("Depth: stdDev=%.2f, noseToSide=%.2f, variance=%.4f", stdDev, noseToSideDepth, variance)

        val isReal = stdDev > DEPTH_VARIANCE_THRESHOLD && noseToSideDepth > 0.5f
        return isReal to stdDev
    }

    /**
     * Analyze texture sharpness of the face crop using Laplacian variance.
     * Real skin: moderate variance (natural texture).
     * Blurry photo: very low variance.
     * Screen (sharp pixels): very high variance or moiré artifacts.
     */
    private fun analyzeTexture(face: Face, bitmap: Bitmap): Pair<Boolean, Double> {
        return try {
            val box = face.boundingBox
            val left = maxOf(0, box.left)
            val top = maxOf(0, box.top)
            val width = minOf(bitmap.width - left, box.width())
            val height = minOf(bitmap.height - top, box.height())
            if (width < 30 || height < 30) return false to 0.0

            val faceCrop = Bitmap.createBitmap(bitmap, left, top, width, height)
            val small = faceCrop.scale(64, 64)
            faceCrop.recycle()

            val laplacianVar = computeLaplacianVariance(small)
            small.recycle()

            Timber.tag(TAG).d("Texture sharpness: %.2f", laplacianVar)

            val isNatural = laplacianVar in TEXTURE_SHARPNESS_MIN..TEXTURE_SHARPNESS_MAX
            isNatural to laplacianVar
        } catch (e: Exception) {
            Timber.tag(TAG).w("Texture analysis error: ${e.message}")
            true to 50.0
        }
    }

    private fun computeLaplacianVariance(bitmap: Bitmap): Double {
        val w = bitmap.width
        val h = bitmap.height
        val pixels = IntArray(w * h)
        bitmap.getPixels(pixels, 0, w, 0, 0, w, h)

        val gray = DoubleArray(w * h)
        for (i in pixels.indices) {
            val r = (pixels[i] shr 16) and 0xff
            val g = (pixels[i] shr 8) and 0xff
            val b = pixels[i] and 0xff
            gray[i] = 0.299 * r + 0.587 * g + 0.114 * b
        }

        // Laplacian kernel: [0,1,0; 1,-4,1; 0,1,0]
        var sum = 0.0
        var sumSq = 0.0
        var count = 0
        for (y in 1 until h - 1) {
            for (x in 1 until w - 1) {
                val lap = -4 * gray[y * w + x] +
                        gray[(y - 1) * w + x] +
                        gray[(y + 1) * w + x] +
                        gray[y * w + (x - 1)] +
                        gray[y * w + (x + 1)]
                sum += lap
                sumSq += lap * lap
                count++
            }
        }
        if (count == 0) return 0.0
        val mean = sum / count
        return (sumSq / count) - (mean * mean)
    }

    /**
     * Detect if face is on a screen by analyzing color channel distribution.
     * Screens emit light with a blue-heavy color temperature.
     * Also checks for unnaturally uniform brightness (backlit screen).
     * Returns (isScreen, blueRatio).
     */
    private fun detectScreenColors(face: Face, bitmap: Bitmap): Pair<Boolean, Float> {
        return try {
            val box = face.boundingBox
            val left = maxOf(0, box.left)
            val top = maxOf(0, box.top)
            val width = minOf(bitmap.width - left, box.width())
            val height = minOf(bitmap.height - top, box.height())
            if (width < 20 || height < 20) return false to 0f

            val faceCrop = Bitmap.createBitmap(bitmap, left, top, width, height)
            val small = faceCrop.scale(32, 32)
            faceCrop.recycle()

            val pixels = IntArray(32 * 32)
            small.getPixels(pixels, 0, 32, 0, 0, 32, 32)
            small.recycle()

            var totalR = 0L; var totalG = 0L; var totalB = 0L
            var brightnessValues = mutableListOf<Int>()

            for (pixel in pixels) {
                val r = (pixel shr 16) and 0xff
                val g = (pixel shr 8) and 0xff
                val b = pixel and 0xff
                totalR += r; totalG += g; totalB += b
                brightnessValues.add((r + g + b) / 3)
            }

            val total = (totalR + totalG + totalB).toFloat()
            if (total < 1f) return false to 0f

            val blueRatio = totalB.toFloat() / total
            val greenRatio = totalG.toFloat() / total

            // Screen detection: blue-heavy color + very uniform brightness
            val meanBrightness = brightnessValues.average()
            val brightnessVar = brightnessValues.map { (it - meanBrightness) * (it - meanBrightness) }.average()
            val brightnessStdDev = sqrt(brightnessVar).toFloat()

            // Screens have high blue ratio and more uniform brightness
            val isScreen = blueRatio > SCREEN_BLUE_RATIO_MAX ||
                    (blueRatio > 0.37f && brightnessStdDev < 30f) ||
                    (brightnessStdDev < 15f && meanBrightness > 100)

            Timber.tag(TAG).d("Screen check: blueRatio=%.3f, brightnessStdDev=%.1f, isScreen=%b",
                blueRatio, brightnessStdDev, isScreen)

            isScreen to blueRatio
        } catch (e: Exception) {
            Timber.tag(TAG).w("Screen color detection error: ${e.message}")
            false to 0f
        }
    }

    /**
     * Track face center position across frames. Real faces have natural
     * micro-movements (breathing, subtle sway) with variable velocity.
     * Photos held by hand move more uniformly.
     */
    private fun analyzeMotion(): Pair<Boolean, Float> {
        if (motionHistory.size < 8) return true to 0f // not enough data yet

        val positions = motionHistory.toList()
        val velocitiesX = mutableListOf<Float>()
        val velocitiesY = mutableListOf<Float>()

        for (i in 1 until positions.size) {
            velocitiesX.add(positions[i].first - positions[i - 1].first)
            velocitiesY.add(positions[i].second - positions[i - 1].second)
        }

        // Velocity variance — real faces have irregular micro-movements
        val varX = computeVariance(velocitiesX)
        val varY = computeVariance(velocitiesY)
        val motionVariance = (varX + varY) / 2f

        // Also check for total stillness (impossibly still = photo on stand)
        val totalMovement = positions.zipWithNext().sumOf { (a, b) ->
            sqrt(((a.first - b.first) * (a.first - b.first) +
                    (a.second - b.second) * (a.second - b.second)).toDouble())
        }.toFloat()

        val avgMovement = totalMovement / positions.size

        Timber.tag(TAG).d("Motion: variance=%.2f, avgMovement=%.2f", motionVariance, avgMovement)

        // Too still is suspicious, but some natural stillness is OK
        val isNatural = motionVariance > MIN_MOTION_VARIANCE || avgMovement > 1.0f
        return isNatural to motionVariance
    }

    private fun computeVariance(values: List<Float>): Float {
        if (values.isEmpty()) return 0f
        val mean = values.average().toFloat()
        return values.map { (it - mean) * (it - mean) }.average().toFloat()
    }

    /**
     * Check that ALL recent embeddings are from the SAME face.
     * If someone swaps a photo in mid-verification, the embeddings will diverge.
     */
    private fun checkEmbeddingConsistency(currentEmbedding: FloatArray): Boolean {
        recentEmbeddings.addLast(currentEmbedding.copyOf())
        if (recentEmbeddings.size > EMBEDDING_HISTORY_SIZE) recentEmbeddings.removeFirst()
        if (recentEmbeddings.size < 3) return true

        val embeddings = recentEmbeddings.toList()
        for (i in 1 until embeddings.size) {
            var dot = 0.0
            for (j in embeddings[i].indices) {
                dot += embeddings[i][j] * embeddings[i - 1][j]
            }
            if (dot < MIN_EMBEDDING_CONSISTENCY) {
                Timber.tag(TAG).w("Embedding inconsistency: frame ${i-1}→$i cosine=%.3f (face swap?)", dot)
                return false
            }
        }
        return true
    }

    /**
     * Check eye naturalness — real eyes have varying open probability
     * across frames, photos have fixed probability.
     */
    private fun analyzeEyeNaturalness(face: Face): Boolean {
        val leftEye = face.leftEyeOpenProbability ?: return false
        val rightEye = face.rightEyeOpenProbability ?: return false

        // Both eyes must be detected with reasonable values
        if (leftEye < 0.01f && rightEye < 0.01f) return false

        // Eyes should be roughly symmetric (real faces)
        val eyeDiff = abs(leftEye - rightEye)
        if (eyeDiff > 0.5f) return false

        // Face tracking ID must exist (proves continuous tracking)
        return face.trackingId != null
    }

    // ── Embedding ───────────────────────────────────────────────────────────────

    fun extractFaceEmbedding(face: Face, bitmap: Bitmap): FloatArray {
        var faceBitmap: Bitmap? = null
        var resizedBitmap: Bitmap? = null
        return try {
            val box = face.boundingBox
            val left = maxOf(0, box.left)
            val top = maxOf(0, box.top)
            val right = minOf(bitmap.width, box.right)
            val bottom = minOf(bitmap.height, box.bottom)
            val w = right - left
            val h = bottom - top
            if (w <= MIN_FACE_DIMENSION || h <= MIN_FACE_DIMENSION) return FloatArray(EMBEDDING_SIZE)

            val padLeft = maxOf(0, (left - w * (FACE_PAD_RATIO - 1) / 2).toInt())
            val padTop = maxOf(0, (top - h * (FACE_PAD_RATIO - 1) / 2).toInt())
            val padRight = minOf(bitmap.width, (right + w * (FACE_PAD_RATIO - 1) / 2).toInt())
            val padBottom = minOf(bitmap.height, (bottom + h * (FACE_PAD_RATIO - 1) / 2).toInt())
            val padW = padRight - padLeft
            val padH = padBottom - padTop
            if (padW < MIN_FACE_DIMENSION || padH < MIN_FACE_DIMENSION) return FloatArray(EMBEDDING_SIZE)

            faceBitmap = Bitmap.createBitmap(bitmap, padLeft, padTop, padW, padH)
            resizedBitmap = faceBitmap.scale(MODEL_INPUT_SIZE, MODEL_INPUT_SIZE)

            val inputBuffer = ByteBuffer
                .allocateDirect(1 * MODEL_INPUT_SIZE * MODEL_INPUT_SIZE * 3 * 4)
                .apply { order(ByteOrder.nativeOrder()) }

            val pixels = IntArray(MODEL_INPUT_SIZE * MODEL_INPUT_SIZE)
            resizedBitmap.getPixels(pixels, 0, MODEL_INPUT_SIZE, 0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE)
            for (pixel in pixels) {
                inputBuffer.putFloat(((pixel shr 16 and 0xff) - IMAGE_MEAN) / IMAGE_STD)
                inputBuffer.putFloat(((pixel shr 8 and 0xff) - IMAGE_MEAN) / IMAGE_STD)
                inputBuffer.putFloat(((pixel and 0xff) - IMAGE_MEAN) / IMAGE_STD)
            }
            inputBuffer.rewind()

            val outputBuffer = Array(1) { FloatArray(EMBEDDING_SIZE) }
            tfliteInterpreter.run(inputBuffer, outputBuffer)

            val embedding = outputBuffer[0]
            val norm = sqrt(embedding.map { it * it }.sum())
            if (norm < 0.0001f) return FloatArray(EMBEDDING_SIZE)
            embedding.indices.forEach { embedding[it] /= norm }
            embedding
        } catch (e: Exception) {
            Timber.tag(TAG).e("extractFaceEmbedding failed: ${e.message}")
            FloatArray(EMBEDDING_SIZE)
        } finally {
            faceBitmap?.recycle()
            resizedBitmap?.recycle()
        }
    }

    fun compareFaces(e1: FloatArray, e2: FloatArray): Double {
        if (e1.size != e2.size || e1.isEmpty()) return 0.0
        var dot = 0.0
        for (i in e1.indices) dot += e1[i] * e2[i]
        val cosineSim = dot.coerceIn(-1.0, 1.0)
        if (cosineSim <= 0.0) return 0.0
        return (cosineSim * 100.0).coerceAtMost(100.0)
    }

    fun embeddingToString(embedding: FloatArray): String =
        embedding.joinToString(",") { java.lang.Float.floatToRawIntBits(it).toString() }

    fun stringToEmbedding(s: String): FloatArray {
        return try {
            s.split(",").map { java.lang.Float.intBitsToFloat(it.trim().toInt()) }.toFloatArray()
        } catch (_: Exception) {
            try { s.split(",").map { it.trim().toFloat() }.toFloatArray() }
            catch (_: Exception) { FloatArray(EMBEDDING_SIZE) }
        }
    }

    // ── Image Conversion ────────────────────────────────────────────────────────

    fun imageProxyToBitmap(imageProxy: ImageProxy): Bitmap? {
        return try {
            val yuvBytes = yuv420ToNv21(imageProxy)
            val yuvImage = YuvImage(
                yuvBytes, ImageFormat.NV21,
                imageProxy.width, imageProxy.height, null
            )
            val out = ByteArrayOutputStream()
            yuvImage.compressToJpeg(Rect(0, 0, imageProxy.width, imageProxy.height), 90, out)
            val rawBitmap = BitmapFactory.decodeByteArray(out.toByteArray(), 0, out.size()) ?: return null

            val rotation = imageProxy.imageInfo.rotationDegrees
            val matrix = Matrix().apply {
                if (rotation != 0) postRotate(rotation.toFloat())
                postScale(-1f, 1f, rawBitmap.width / 2f, rawBitmap.height / 2f)
            }
            val corrected = Bitmap.createBitmap(rawBitmap, 0, 0, rawBitmap.width, rawBitmap.height, matrix, false)
            rawBitmap.recycle()
            corrected
        } catch (e: Exception) {
            Timber.tag(TAG).e("imageProxyToBitmap error: ${e.message}")
            null
        }
    }

    private fun yuv420ToNv21(imageProxy: ImageProxy): ByteArray {
        val w = imageProxy.width; val h = imageProxy.height
        val yP = imageProxy.planes[0]; val uP = imageProxy.planes[1]; val vP = imageProxy.planes[2]
        val yB = yP.buffer; val uB = uP.buffer; val vB = vP.buffer
        val yRS = yP.rowStride; val uvRS = uP.rowStride; val uvPS = uP.pixelStride
        val nv21 = ByteArray(w * h * 3 / 2)
        var pos = 0
        for (row in 0 until h) { yB.position(row * yRS); yB.get(nv21, pos, w); pos += w }
        for (row in 0 until h / 2) {
            for (col in 0 until w / 2) {
                val idx = row * uvRS + col * uvPS
                vB.position(idx); nv21[pos++] = vB.get()
                uB.position(idx); nv21[pos++] = uB.get()
            }
        }
        return nv21
    }

    // ── Passive Quality Check ───────────────────────────────────────────────────

    data class LivenessResult(
        val isLive: Boolean, val score: Float, val message: String,
        val checks: Map<String, Boolean>
    )

    fun checkPassiveLiveness(face: Face, bitmap: Bitmap? = null): LivenessResult {
        val checks = mutableMapOf<String, Boolean>()
        val box = face.boundingBox
        val area = (box.right - box.left) * (box.bottom - box.top)
        checks["face_size"] = area in FACE_AREA_MIN..FACE_AREA_MAX
        checks["landmarks"] = face.getLandmark(FaceLandmark.LEFT_EYE) != null &&
                face.getLandmark(FaceLandmark.RIGHT_EYE) != null &&
                face.getLandmark(FaceLandmark.NOSE_BASE) != null
        val fc = face.getContour(FaceContour.FACE)
        checks["contours"] = fc != null && fc.points.size >= 25

        return LivenessResult(
            isLive = checks.values.all { it },
            score = if (checks.values.all { it }) 100f else 40f,
            message = when {
                !checks["face_size"]!! -> if (area < FACE_AREA_MIN) "Move closer" else "Move back"
                !checks["landmarks"]!! -> "Show full face"
                !checks["contours"]!! -> "Face unclear"
                else -> "OK"
            },
            checks = checks
        )
    }

    // ── Combined Verification with Anti-Spoofing ────────────────────────────────

    data class VerificationResult(
        val success: Boolean, val faceMatch: Double, val livenessScore: Float,
        val isLive: Boolean, val embedding: FloatArray?, val message: String,
        val framesVerified: Int, val requiredFrames: Int
    )

    /**
     * Strict 6-layer verification pipeline. Every frame must pass ALL layers:
     *
     * 1. Quality check (face size, landmarks, contours)
     * 2. Anti-spoofing (3D depth + texture + screen color + motion) — EVERY frame
     * 3. Identity match against stored embedding (cosine ≥ 0.6)
     * 4. Embedding consistency (same face across ALL frames — detects face swap)
     * 5. Pre-liveness identity lock (N frames of identity match BEFORE challenges start)
     * 6. Active liveness challenges (blink + head turn)
     * 7. Post-liveness sustained match (N more frames after liveness)
     * 8. Depth history validation (consistent 3D depth across session)
     *
     * If ANY layer fails, progress resets. Face swap = full reset.
     */
    suspend fun verifyFaceWithLiveness(
        bitmap: Bitmap,
        storedEmbedding: FloatArray,
        confidenceThreshold: Double,
        enableLiveness: Boolean = true
    ): VerificationResult {
        try {
            frameCount++

            val faces = detectFaces(bitmap)
            if (faces.isEmpty()) {
                return fail("No face detected — look at the camera")
            }
            if (faces.size > 1) {
                return fail("Multiple faces — only you should be visible")
            }

            val face = faces[0]

            // ── Layer 1: Basic quality ──
            val quality = checkPassiveLiveness(face, bitmap)
            if (!quality.isLive) {
                return fail(quality.message)
            }

            // ── Layer 2: Anti-spoofing EVERY frame ──
            val spoofResult = checkAntiSpoofing(bitmap, face)
            if (!spoofResult.isReal) {
                spoofDetected = true
                spoofReason = spoofResult.reason
                spoofPassStreak = 0
                Timber.tag(TAG).w("Spoof detected: ${spoofResult.reason} (score=${spoofResult.confidence})")
            } else {
                spoofPassStreak++
                totalSpoofPasses++
                if (spoofPassStreak >= MIN_SPOOF_PASS_STREAK) {
                    spoofDetected = false
                    spoofReason = ""
                }
            }

            if (spoofDetected) {
                postLivenessMatchCount = 0
                preLivenessIdentityCount = 0
                return VerificationResult(
                    success = false, faceMatch = 0.0, livenessScore = 0f,
                    isLive = false, embedding = null,
                    message = "Photo/screen detected — use your real face",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }

            // ── Layer 3: Identity match on EVERY frame ──
            val embedding = extractFaceEmbedding(face, bitmap)
            val confidence = compareFaces(embedding, storedEmbedding)

            if (confidence < MIN_IDENTITY_MATCH) {
                identityFailStreak++
                if (identityFailStreak >= MAX_IDENTITY_FAILS) {
                    fullReset()
                    return VerificationResult(
                        success = false, faceMatch = confidence, livenessScore = 0f,
                        isLive = false, embedding = embedding,
                        message = "Face doesn't match — start over",
                        framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                    )
                }
                postLivenessMatchCount = 0
                preLivenessIdentityCount = 0
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 0f,
                    isLive = false, embedding = embedding,
                    message = "Face match low (${confidence.toInt()}%) — hold steady",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }
            identityFailStreak = 0

            // ── Layer 4: Confidence jump detection (face swap) ──
            val prev = lastMatchConfidence ?: 0.0
            if (prev > 10.0 && abs(confidence - prev) > MAX_CONFIDENCE_JUMP) {
                Timber.tag(TAG).w("Confidence jump: $prev -> $confidence — possible swap")
                fullReset()
                return fail("Face changed — start over")
            }
            lastMatchConfidence = confidence

            // ── Layer 5: Embedding consistency (same face across ALL frames) ──
            if (!checkEmbeddingConsistency(embedding)) {
                fullReset()
                Timber.tag(TAG).w("Face swap detected via embedding inconsistency")
                return fail("Face changed — start over")
            }

            // ── Layer 6: Pre-liveness identity lock ──
            // Must have N consistent identity frames BEFORE liveness challenges begin
            if (enableLiveness && !identityLocked) {
                preLivenessIdentityCount++
                if (preLivenessIdentityCount < PRE_LIVENESS_IDENTITY_FRAMES) {
                    return VerificationResult(
                        success = false, faceMatch = confidence, livenessScore = 0f,
                        isLive = false, embedding = embedding,
                        message = "Verifying identity... hold steady",
                        framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                    )
                }
                identityLocked = true
                Timber.tag(TAG).d("Identity locked after $preLivenessIdentityCount frames")
            }

            // ── Layer 7: Active liveness challenges ──
            if (enableLiveness) {
                if (livenessState == null) startLivenessChallenge()

                val state = livenessState!!
                if (!state.allPassed) {
                    val lr = processLivenessFrame(face)
                    if (lr.status != LivenessStatus.ALL_PASSED) {
                        return VerificationResult(
                            success = false, faceMatch = confidence,
                            livenessScore = lr.progress * 100f,
                            isLive = false, embedding = embedding,
                            message = lr.instruction,
                            framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                        )
                    }
                }
            }

            // ── Layer 8: Post-liveness strict matching ──
            if (confidence < confidenceThreshold) {
                postLivenessMatchCount = 0
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 100f,
                    isLive = true, embedding = embedding,
                    message = "Face match: ${confidence.toInt()}% (need ${confidenceThreshold.toInt()}%)",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }

            // ── Layer 9: Depth history validation ──
            if (depthPassCount < 3 && frameCount > 6) {
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 100f,
                    isLive = true, embedding = embedding,
                    message = "Verifying face depth... hold steady",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }

            // ── Layer 10: Require minimum total anti-spoofing passes ──
            if (totalSpoofPasses < 4) {
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 100f,
                    isLive = true, embedding = embedding,
                    message = "Verifying... hold steady",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }

            postLivenessMatchCount++
            if (postLivenessMatchCount < REQUIRED_MATCH_FRAMES) {
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 100f,
                    isLive = true, embedding = embedding,
                    message = "Verifying... hold steady",
                    framesVerified = postLivenessMatchCount, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }

            return VerificationResult(
                success = true, faceMatch = confidence, livenessScore = 100f,
                isLive = true, embedding = embedding,
                message = "Verified!",
                framesVerified = postLivenessMatchCount, requiredFrames = REQUIRED_MATCH_FRAMES
            )
        } catch (e: Exception) {
            Timber.tag(TAG).e("verifyFaceWithLiveness error: ${e.message}")
            fullReset()
            return fail("Error: ${e.message}")
        }
    }

    private fun fail(msg: String) = VerificationResult(
        success = false, faceMatch = 0.0, livenessScore = 0f,
        isLive = false, embedding = null, message = msg,
        framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
    )

    private fun fullReset() {
        livenessState = null
        postLivenessMatchCount = 0
        lastMatchConfidence = 0.0
        identityFailStreak = 0
        frameCount = 0
        spoofDetected = false
        spoofReason = ""
        motionHistory.clear()
        depthHistory.clear()
        depthPassCount = 0
        spoofPassStreak = 0
        totalSpoofPasses = 0
        recentEmbeddings.clear()
        preLivenessIdentityCount = 0
        identityLocked = false
    }

    // ── Liveness Frame Processing ───────────────────────────────────────────────

    enum class LivenessStatus {
        NOT_STARTED, IN_PROGRESS, CHALLENGE_PASSED, CHALLENGE_TIMEOUT, ALL_PASSED
    }

    data class LivenessFrameResult(
        val status: LivenessStatus, val instruction: String, val progress: Float
    )

    private fun processLivenessFrame(face: Face): LivenessFrameResult {
        val state = livenessState ?: return LivenessFrameResult(
            LivenessStatus.NOT_STARTED, "Initializing...", 0f
        )
        if (state.allPassed) return LivenessFrameResult(
            LivenessStatus.ALL_PASSED, "Hold steady...", 1f
        )

        val challenge = state.currentChallenge ?: return LivenessFrameResult(
            LivenessStatus.ALL_PASSED, "Hold steady...", 1f
        )

        val elapsed = System.currentTimeMillis() - state.startTime
        if (elapsed > CHALLENGE_TIMEOUT_MS) {
            val newList = state.challenges.toMutableList()
            newList[state.currentIndex] = Challenge.entries.toList().random()
            livenessState = LivenessState(
                challenges = newList, currentIndex = state.currentIndex,
                startTime = System.currentTimeMillis()
            )
            return LivenessFrameResult(
                LivenessStatus.CHALLENGE_TIMEOUT,
                "Timed out — ${getLivenessInstruction()}", state.progress
            )
        }

        val passed = when (challenge) {
            Challenge.BLINK -> processBlink(face, state)
            Challenge.TURN_LEFT -> processHeadTurn(face, isLeft = true, state)
            Challenge.TURN_RIGHT -> processHeadTurn(face, isLeft = false, state)
        }

        if (passed) {
            val nextIdx = state.currentIndex + 1
            if (nextIdx >= state.challenges.size) {
                livenessState = state.copy(currentIndex = nextIdx, allPassed = true)
                return LivenessFrameResult(LivenessStatus.ALL_PASSED, "Liveness verified!", 1f)
            }
            livenessState = state.copy(
                currentIndex = nextIdx, startTime = System.currentTimeMillis(),
                blinkPhase = BlinkPhase.WAITING_CLOSE, blinkCloseTime = 0L,
                turnDetected = false
            )
            return LivenessFrameResult(
                LivenessStatus.CHALLENGE_PASSED,
                "Good! Now: ${getLivenessInstruction()}",
                nextIdx.toFloat() / state.challenges.size
            )
        }

        return LivenessFrameResult(LivenessStatus.IN_PROGRESS, getLivenessInstruction(), state.progress)
    }

    private fun processBlink(face: Face, state: LivenessState): Boolean {
        val leftEye = face.leftEyeOpenProbability ?: return false
        val rightEye = face.rightEyeOpenProbability ?: return false
        val avgOpen = (leftEye + rightEye) / 2f

        val eyeDiff = abs(leftEye - rightEye)
        if (eyeDiff > 0.3f) return false

        val contour = face.getContour(FaceContour.FACE)
        if (contour == null || contour.points.size < 25) return false

        val roll = abs(face.headEulerAngleZ)
        if (roll > 15f) return false

        return when (state.blinkPhase) {
            BlinkPhase.WAITING_CLOSE -> {
                if (avgOpen < BLINK_CLOSE_THRESHOLD && leftEye < 0.3f && rightEye < 0.3f) {
                    livenessState = state.copy(
                        blinkPhase = BlinkPhase.WAITING_OPEN,
                        blinkCloseTime = System.currentTimeMillis()
                    )
                    Timber.tag(TAG).d("Blink: eyes closed ($avgOpen)")
                }
                false
            }
            BlinkPhase.WAITING_OPEN -> {
                val blinkDuration = System.currentTimeMillis() - state.blinkCloseTime
                if (avgOpen > BLINK_OPEN_THRESHOLD) {
                    if (blinkDuration in 80..800) {
                        Timber.tag(TAG).d("Blink detected: ${blinkDuration}ms")
                        livenessState = state.copy(blinkPhase = BlinkPhase.DONE)
                        true
                    } else {
                        Timber.tag(TAG).w("Blink rejected: ${blinkDuration}ms (unnatural)")
                        livenessState = state.copy(blinkPhase = BlinkPhase.WAITING_CLOSE, blinkCloseTime = 0L)
                        false
                    }
                } else if (blinkDuration > 1500) {
                    livenessState = state.copy(blinkPhase = BlinkPhase.WAITING_CLOSE, blinkCloseTime = 0L)
                    false
                } else false
            }
            BlinkPhase.DONE -> true
        }
    }

    private fun processHeadTurn(face: Face, isLeft: Boolean, state: LivenessState): Boolean {
        val yaw = face.headEulerAngleY
        val roll = abs(face.headEulerAngleZ)

        if (roll > 15f) return false

        val turned = if (isLeft) yaw > HEAD_TURN_THRESHOLD else yaw < -HEAD_TURN_THRESHOLD

        if (!state.turnDetected && turned) {
            livenessState = state.copy(turnDetected = true)
            Timber.tag(TAG).d("Head turn: yaw=$yaw (${if (isLeft) "left" else "right"})")
        }

        if (state.turnDetected && abs(yaw) < HEAD_CENTER_THRESHOLD) {
            Timber.tag(TAG).d("Head returned center — PASS")
            return true
        }
        return false
    }

    fun resetVerification() = fullReset()

    // ── Legacy Compatibility ────────────────────────────────────────────────────

    data class LivenessCheckResult(
        val isLive: Boolean, val confidence: Float, val issues: List<String> = emptyList()
    )

    fun checkLiveness(face: Face, bitmap: Bitmap? = null): LivenessCheckResult {
        val r = checkPassiveLiveness(face, bitmap)
        return LivenessCheckResult(r.isLive, r.score, if (!r.isLive) listOf(r.message) else emptyList())
    }

    data class EyeBlinkDetection(
        val eyesClosed: Boolean, val leftEyeClosed: Boolean,
        val rightEyeClosed: Boolean, val reason: String
    )

    fun detectEyeBlink(face: Face): EyeBlinkDetection {
        val l = face.leftEyeOpenProbability ?: 1f
        val r = face.rightEyeOpenProbability ?: 1f
        val t = 0.3f
        return EyeBlinkDetection(l < t && r < t, l < t, r < t, when {
            l < t && r < t -> "Both eyes closed"
            l < t -> "Left eye closed"
            r < t -> "Right eye closed"
            else -> "Eyes open"
        })
    }

    fun close() {
        detector.close()
        meshDetector.close()
        fullReset()
        try { tfliteInterpreter.close() } catch (_: Exception) {}
    }
}
