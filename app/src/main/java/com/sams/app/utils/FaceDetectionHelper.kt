package com.sams.app.utils

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
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

        private const val BLINK_CLOSE_THRESHOLD = 0.25f
        private const val BLINK_OPEN_THRESHOLD = 0.50f
        private const val HEAD_TURN_THRESHOLD = 18f
        private const val HEAD_CENTER_THRESHOLD = 10f
        private const val FACE_AREA_MIN = 10000
        private const val FACE_AREA_MAX = 600000
        private const val CHALLENGE_TIMEOUT_MS = 12000L

        private const val REQUIRED_MATCH_FRAMES = 3
        private const val MAX_CONFIDENCE_JUMP = 15.0
        private const val MIN_IDENTITY_MATCH = 55.0
        private const val MAX_IDENTITY_FAILS = 3

        private const val EMBEDDING_HISTORY_SIZE = 5
        private const val MIN_EMBEDDING_CONSISTENCY = 0.50
        private const val PRE_LIVENESS_IDENTITY_FRAMES = 2
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
        val allPassed: Boolean = false
    ) {
        val currentChallenge: Challenge?
            get() = if (currentIndex < challenges.size) challenges[currentIndex] else null
        val progress: Float
            get() = if (challenges.isEmpty()) 1f else currentIndex.toFloat() / challenges.size
    }

    private var livenessState: LivenessState? = null
    private var postLivenessMatchCount = 0
    private var lastMatchConfidence = 0.0
    private var identityFailStreak = 0

    private val recentEmbeddings = ArrayDeque<FloatArray>(EMBEDDING_HISTORY_SIZE + 1)
    private var preLivenessIdentityCount = 0
    private var identityLocked = false

    fun startLivenessChallenge() {
        val challenge = listOf(Challenge.BLINK, Challenge.TURN_LEFT, Challenge.TURN_RIGHT).random()
        livenessState = LivenessState(challenges = listOf(challenge))
        postLivenessMatchCount = 0
        lastMatchConfidence = 0.0
        recentEmbeddings.clear()
        preLivenessIdentityCount = 0
        identityLocked = false
        Timber.tag(TAG).d("Liveness challenge: $challenge")
    }

    fun getLivenessInstruction(): String {
        val state = livenessState ?: return "Initializing..."
        if (state.allPassed) return "Hold steady..."
        return when (state.currentChallenge) {
            Challenge.BLINK -> "Blink your eyes"
            Challenge.TURN_LEFT -> "Turn head left"
            Challenge.TURN_RIGHT -> "Turn head right"
            null -> "Hold steady..."
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
        checks["contours"] = fc != null && fc.points.size >= 20

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

    /**
     * Verify consecutive embeddings are from the same face.
     * Catches mid-verification face swaps.
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
                Timber.tag(TAG).w("Embedding inconsistency: frame ${i - 1}→$i cosine=%.3f", dot)
                return false
            }
        }
        return true
    }

    // ── Streamlined Verification Pipeline ────────────────────────────────────────

    data class VerificationResult(
        val success: Boolean, val faceMatch: Double, val livenessScore: Float,
        val isLive: Boolean, val embedding: FloatArray?, val message: String,
        val framesVerified: Int, val requiredFrames: Int
    )

    /**
     * Fast 5-step verification:
     * 1. Quality (face size, landmarks)
     * 2. Identity match against stored embedding (EVERY frame)
     * 3. Embedding consistency (detect face swap)
     * 4. Active liveness (single challenge: blink or head turn)
     * 5. Post-liveness confirmation (3 consecutive matching frames)
     */
    suspend fun verifyFaceWithLiveness(
        bitmap: Bitmap,
        storedEmbedding: FloatArray,
        confidenceThreshold: Double,
        enableLiveness: Boolean = true
    ): VerificationResult {
        try {
            val faces = detectFaces(bitmap)
            if (faces.isEmpty()) return fail("No face detected — look at the camera")
            if (faces.size > 1) return fail("Multiple faces — only you should be visible")

            val face = faces[0]

            // ── Step 1: Quality ──
            val quality = checkPassiveLiveness(face, bitmap)
            if (!quality.isLive) return fail(quality.message)

            // ── Step 2: Identity match EVERY frame ──
            val embedding = extractFaceEmbedding(face, bitmap)
            val confidence = compareFaces(embedding, storedEmbedding)

            if (confidence < MIN_IDENTITY_MATCH) {
                identityFailStreak++
                if (identityFailStreak >= MAX_IDENTITY_FAILS) {
                    fullReset()
                    return VerificationResult(
                        success = false, faceMatch = confidence, livenessScore = 0f,
                        isLive = false, embedding = embedding,
                        message = "Face doesn't match — try again",
                        framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                    )
                }
                postLivenessMatchCount = 0
                preLivenessIdentityCount = 0
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 0f,
                    isLive = false, embedding = embedding,
                    message = "Face match low (${confidence.toInt()}%)",
                    framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                )
            }
            identityFailStreak = 0

            // ── Step 3: Confidence jump + embedding consistency ──
            if (lastMatchConfidence > 10.0 && abs(confidence - lastMatchConfidence) > MAX_CONFIDENCE_JUMP) {
                Timber.tag(TAG).w("Confidence jump: $lastMatchConfidence -> $confidence")
                fullReset()
                return fail("Face changed — start over")
            }
            lastMatchConfidence = confidence

            if (!checkEmbeddingConsistency(embedding)) {
                fullReset()
                return fail("Face changed — start over")
            }

            // ── Step 4: Pre-liveness identity lock ──
            if (enableLiveness && !identityLocked) {
                preLivenessIdentityCount++
                if (preLivenessIdentityCount < PRE_LIVENESS_IDENTITY_FRAMES) {
                    return VerificationResult(
                        success = false, faceMatch = confidence, livenessScore = 0f,
                        isLive = false, embedding = embedding,
                        message = "Verifying identity...",
                        framesVerified = 0, requiredFrames = REQUIRED_MATCH_FRAMES
                    )
                }
                identityLocked = true
                Timber.tag(TAG).d("Identity locked after $preLivenessIdentityCount frames")
            }

            // ── Step 5: Active liveness ──
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

            // ── Step 6: Post-liveness confirmation ──
            if (confidence < confidenceThreshold) {
                postLivenessMatchCount = 0
                return VerificationResult(
                    success = false, faceMatch = confidence, livenessScore = 100f,
                    isLive = true, embedding = embedding,
                    message = "Face match: ${confidence.toInt()}% (need ${confidenceThreshold.toInt()}%)",
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
            val newChallenge = Challenge.entries.toList().random()
            livenessState = LivenessState(
                challenges = listOf(newChallenge), currentIndex = 0,
                startTime = System.currentTimeMillis()
            )
            return LivenessFrameResult(
                LivenessStatus.CHALLENGE_TIMEOUT,
                "Timed out — ${getLivenessInstruction()}", 0f
            )
        }

        val passed = when (challenge) {
            Challenge.BLINK -> processBlink(face, state)
            Challenge.TURN_LEFT -> processHeadTurn(face, isLeft = true, state)
            Challenge.TURN_RIGHT -> processHeadTurn(face, isLeft = false, state)
        }

        if (passed) {
            livenessState = state.copy(currentIndex = state.currentIndex + 1, allPassed = true)
            return LivenessFrameResult(LivenessStatus.ALL_PASSED, "Liveness verified!", 1f)
        }

        return LivenessFrameResult(LivenessStatus.IN_PROGRESS, getLivenessInstruction(), state.progress)
    }

    private fun processBlink(face: Face, state: LivenessState): Boolean {
        val leftEye = face.leftEyeOpenProbability ?: return false
        val rightEye = face.rightEyeOpenProbability ?: return false
        val avgOpen = (leftEye + rightEye) / 2f

        if (abs(leftEye - rightEye) > 0.35f) return false

        val roll = abs(face.headEulerAngleZ)
        if (roll > 20f) return false

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
                    if (blinkDuration in 60..900) {
                        Timber.tag(TAG).d("Blink detected: ${blinkDuration}ms")
                        livenessState = state.copy(blinkPhase = BlinkPhase.DONE)
                        true
                    } else {
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
        if (roll > 20f) return false

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
        fullReset()
        try { tfliteInterpreter.close() } catch (_: Exception) {}
    }
}
