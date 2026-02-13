package com.sams.app.ui.student

import android.Manifest
import android.content.pm.PackageManager
import android.graphics.Bitmap
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import androidx.fragment.app.viewModels
import com.sams.app.databinding.FragmentMarkAttendanceBinding
import com.sams.app.ui.viewmodel.StudentViewModel
import com.sams.app.ui.viewmodel.UiState
import com.sams.app.utils.FaceDetectionHelper
import com.sams.app.utils.LocationHelper
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Mark Attendance Fragment
 * Handles GPS + Face verification for attendance marking
 */
class MarkAttendanceFragment : Fragment() {
    
    private var _binding: FragmentMarkAttendanceBinding? = null
    private val binding get() = _binding!!
    
    private val viewModel: StudentViewModel by viewModels()
    
    private lateinit var faceHelper: FaceDetectionHelper
    private lateinit var locationHelper: LocationHelper
    private lateinit var cameraExecutor: ExecutorService
    
    private var imageCapture: ImageCapture? = null
    
    // Schedule info passed from parent
    private var scheduleId: Int = 0
    private var teacherLatitude: Double = 0.0
    private var teacherLongitude: Double = 0.0
    private var subjectName: String = ""
    
    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        val cameraGranted = permissions[Manifest.permission.CAMERA] == true
        val locationGranted = permissions[Manifest.permission.ACCESS_FINE_LOCATION] == true
        
        if (cameraGranted && locationGranted) {
            startCamera()
        } else {
            Toast.makeText(requireContext(), "Camera and Location permissions are required", Toast.LENGTH_LONG).show()
        }
    }
    
    companion object {
        private const val ARG_SCHEDULE_ID = "schedule_id"
        private const val ARG_TEACHER_LAT = "teacher_lat"
        private const val ARG_TEACHER_LON = "teacher_lon"
        private const val ARG_SUBJECT_NAME = "subject_name"
        
        fun newInstance(scheduleId: Int, teacherLat: Double, teacherLon: Double, subjectName: String): MarkAttendanceFragment {
            return MarkAttendanceFragment().apply {
                arguments = Bundle().apply {
                    putInt(ARG_SCHEDULE_ID, scheduleId)
                    putDouble(ARG_TEACHER_LAT, teacherLat)
                    putDouble(ARG_TEACHER_LON, teacherLon)
                    putString(ARG_SUBJECT_NAME, subjectName)
                }
            }
        }
    }
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        arguments?.let {
            scheduleId = it.getInt(ARG_SCHEDULE_ID)
            teacherLatitude = it.getDouble(ARG_TEACHER_LAT)
            teacherLongitude = it.getDouble(ARG_TEACHER_LON)
            subjectName = it.getString(ARG_SUBJECT_NAME, "")
        }
    }
    
    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentMarkAttendanceBinding.inflate(inflater, container, false)
        return binding.root
    }
    
    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        
        // Initialize helpers
        faceHelper = FaceDetectionHelper()
        locationHelper = LocationHelper(requireContext())
        cameraExecutor = Executors.newSingleThreadExecutor()
        
        viewModel.initHelpers(faceHelper, locationHelper)
        
        setupUI()
        observeState()
        checkPermissions()
    }
    
    private fun setupUI() {
        binding.tvSubjectName.text = subjectName
        
        binding.btnCapture.setOnClickListener {
            captureAndVerify()
        }
        
        binding.btnRetry.setOnClickListener {
            binding.layoutResult.visibility = View.GONE
            binding.layoutCamera.visibility = View.VISIBLE
            startCamera()
        }
    }
    
    private fun observeState() {
        viewModel.attendanceState.observe(viewLifecycleOwner) { state ->
            when (state) {
                is UiState.Loading -> {
                    binding.progressBar.visibility = View.VISIBLE
                    binding.btnCapture.isEnabled = false
                }
                is UiState.Success -> {
                    binding.progressBar.visibility = View.GONE
                    showSuccess(state.data)
                }
                is UiState.Error -> {
                    binding.progressBar.visibility = View.GONE
                    binding.btnCapture.isEnabled = true
                    showError(state.message)
                }
            }
        }
    }
    
    private fun checkPermissions() {
        val cameraPermission = ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.CAMERA)
        val locationPermission = ContextCompat.checkSelfPermission(requireContext(), Manifest.permission.ACCESS_FINE_LOCATION)
        
        if (cameraPermission == PackageManager.PERMISSION_GRANTED && 
            locationPermission == PackageManager.PERMISSION_GRANTED) {
            startCamera()
        } else {
            permissionLauncher.launch(arrayOf(
                Manifest.permission.CAMERA,
                Manifest.permission.ACCESS_FINE_LOCATION
            ))
        }
    }
    
    private fun startCamera() {
        val cameraProviderFuture = ProcessCameraProvider.getInstance(requireContext())
        
        cameraProviderFuture.addListener({
            val cameraProvider = cameraProviderFuture.get()
            
            // Preview
            val preview = Preview.Builder().build().also {
                it.setSurfaceProvider(binding.previewView.surfaceProvider)
            }
            
            // Image capture
            imageCapture = ImageCapture.Builder()
                .setCaptureMode(ImageCapture.CAPTURE_MODE_MINIMIZE_LATENCY)
                .build()
            
            // Select front camera
            val cameraSelector = CameraSelector.DEFAULT_FRONT_CAMERA
            
            try {
                cameraProvider.unbindAll()
                cameraProvider.bindToLifecycle(
                    viewLifecycleOwner,
                    cameraSelector,
                    preview,
                    imageCapture
                )
            } catch (e: Exception) {
                Toast.makeText(requireContext(), "Failed to start camera: ${e.message}", Toast.LENGTH_SHORT).show()
            }
        }, ContextCompat.getMainExecutor(requireContext()))
    }
    
    private fun captureAndVerify() {
        val imageCapture = imageCapture ?: return
        
        imageCapture.takePicture(
            ContextCompat.getMainExecutor(requireContext()),
            object : ImageCapture.OnImageCapturedCallback() {
                override fun onCaptureSuccess(image: ImageProxy) {
                    val bitmap = imageProxyToBitmap(image)
                    image.close()
                    
                    if (bitmap != null) {
                        viewModel.markAttendance(scheduleId, bitmap, teacherLatitude, teacherLongitude)
                    } else {
                        Toast.makeText(requireContext(), "Failed to capture image", Toast.LENGTH_SHORT).show()
                    }
                }
                
                override fun onError(exception: ImageCaptureException) {
                    Toast.makeText(requireContext(), "Capture failed: ${exception.message}", Toast.LENGTH_SHORT).show()
                }
            }
        )
    }
    
    private fun imageProxyToBitmap(image: ImageProxy): Bitmap? {
        val buffer = image.planes[0].buffer
        val bytes = ByteArray(buffer.remaining())
        buffer.get(bytes)
        return android.graphics.BitmapFactory.decodeByteArray(bytes, 0, bytes.size)
    }
    
    private fun showSuccess(data: com.sams.app.data.models.MarkAttendanceResponse) {
        binding.layoutCamera.visibility = View.GONE
        binding.layoutResult.visibility = View.VISIBLE
        
        binding.ivResultIcon.setImageResource(android.R.drawable.ic_dialog_info) // Use success icon
        binding.tvResultTitle.text = "Attendance Marked!"
        binding.tvResultMessage.text = """
            Status: ${data.verificationStatus}
            Face Confidence: ${data.faceConfidence?.toInt()}%
            Distance: ${data.distanceMeters?.toInt()}m
        """.trimIndent()
        
        binding.btnRetry.visibility = View.GONE
    }
    
    private fun showError(message: String) {
        binding.layoutCamera.visibility = View.GONE
        binding.layoutResult.visibility = View.VISIBLE
        
        binding.ivResultIcon.setImageResource(android.R.drawable.ic_dialog_alert) // Use error icon
        binding.tvResultTitle.text = "Verification Failed"
        binding.tvResultMessage.text = message
        
        binding.btnRetry.visibility = View.VISIBLE
    }
    
    override fun onDestroyView() {
        super.onDestroyView()
        cameraExecutor.shutdown()
        _binding = null
    }
}

// ==================== Sample Layout (fragment_mark_attendance.xml) ====================
/*
<?xml version="1.0" encoding="utf-8"?>
<androidx.constraintlayout.widget.ConstraintLayout
    xmlns:android="http://schemas.android.com/apk/res/android"
    xmlns:app="http://schemas.android.com/apk/res-auto"
    android:layout_width="match_parent"
    android:layout_height="match_parent">

    <TextView
        android:id="@+id/tvSubjectName"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:textSize="20sp"
        android:textStyle="bold"
        android:padding="16dp"
        app:layout_constraintTop_toTopOf="parent"
        app:layout_constraintStart_toStartOf="parent"
        app:layout_constraintEnd_toEndOf="parent"/>

    <FrameLayout
        android:id="@+id/layoutCamera"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        app:layout_constraintTop_toBottomOf="@id/tvSubjectName"
        app:layout_constraintBottom_toTopOf="@id/btnCapture">

        <androidx.camera.view.PreviewView
            android:id="@+id/previewView"
            android:layout_width="match_parent"
            android:layout_height="match_parent"/>

        <View
            android:layout_width="250dp"
            android:layout_height="300dp"
            android:layout_gravity="center"
            android:background="@drawable/face_overlay"/>

    </FrameLayout>

    <LinearLayout
        android:id="@+id/layoutResult"
        android:layout_width="match_parent"
        android:layout_height="0dp"
        android:orientation="vertical"
        android:gravity="center"
        android:visibility="gone"
        app:layout_constraintTop_toBottomOf="@id/tvSubjectName"
        app:layout_constraintBottom_toTopOf="@id/btnCapture">

        <ImageView
            android:id="@+id/ivResultIcon"
            android:layout_width="96dp"
            android:layout_height="96dp"/>

        <TextView
            android:id="@+id/tvResultTitle"
            android:layout_width="wrap_content"
            android:layout_height="wrap_content"
            android:textSize="24sp"
            android:textStyle="bold"
            android:layout_marginTop="16dp"/>

        <TextView
            android:id="@+id/tvResultMessage"
            android:layout_width="wrap_content"
            android:layout_height="wrap_content"
            android:textSize="16sp"
            android:layout_marginTop="8dp"
            android:gravity="center"/>

    </LinearLayout>

    <com.google.android.material.button.MaterialButton
        android:id="@+id/btnCapture"
        android:layout_width="match_parent"
        android:layout_height="56dp"
        android:layout_margin="16dp"
        android:text="Capture & Verify"
        app:layout_constraintBottom_toTopOf="@id/btnRetry"/>

    <com.google.android.material.button.MaterialButton
        android:id="@+id/btnRetry"
        style="@style/Widget.MaterialComponents.Button.OutlinedButton"
        android:layout_width="match_parent"
        android:layout_height="56dp"
        android:layout_marginHorizontal="16dp"
        android:layout_marginBottom="16dp"
        android:text="Retry"
        android:visibility="gone"
        app:layout_constraintBottom_toBottomOf="parent"/>

    <ProgressBar
        android:id="@+id/progressBar"
        android:layout_width="wrap_content"
        android:layout_height="wrap_content"
        android:visibility="gone"
        app:layout_constraintTop_toTopOf="parent"
        app:layout_constraintBottom_toBottomOf="parent"
        app:layout_constraintStart_toStartOf="parent"
        app:layout_constraintEnd_toEndOf="parent"/>

</androidx.constraintlayout.widget.ConstraintLayout>
*/
