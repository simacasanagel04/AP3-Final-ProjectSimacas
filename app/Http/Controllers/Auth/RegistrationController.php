<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

/**
 * app/Http/Controllers/Auth/RegistrationController.php
 * 
 * SESSION-BASED REGISTRATION FLOW
 * ================================
 * All registration data is stored in encrypted Laravel session
 * Database insertion only happens AFTER password creation
 * This prevents "user already exists" issues during multi-step registration
 * 
 * Flow:
 * 1. User fills multi-step form (data stored in session only)
 * 2. Email uniqueness validated before storing in session
 * 3. User redirected to password creation page
 * 4. After password entry, ALL data saved to DB in one transaction
 * 5. User auto-logged in and redirected to dashboard
 */
class RegistrationController extends Controller
{
    // ============================================================================
    // STUDENT REGISTRATION
    // ============================================================================

    /**
     * Display the student registration form
     */
    public function showStudentRegistrationForm()
    {
        $instruments = DB::table('instrument')
            ->where('is_active', true)
            ->orderBy('instrument_name')
            ->get();

        return view('auth.register.student', compact('instruments'));
    }

    /**
     * Process student registration - STORES IN SESSION ONLY
     */
    public function registerStudent(Request $request)
    {
        // Validate ALL data including email uniqueness
        $validated = $request->validate([
            'user_email' => 'required|email|unique:user_account,user_email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|regex:/^[0-9]{11}$/',
            'middle_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
            'instrument_id' => 'nullable|exists:instrument,instrument_id',
            'skill_level' => 'nullable|in:beginner,intermediate,advanced,expert',
            'music_goals' => 'nullable|string',
        ], [
            'user_email.unique' => 'This email is already registered.',
            'phone.regex' => 'Phone number must be exactly 11 digits.',
        ]);

        //STORE IN SESSION - NOT DATABASE YET
        session([
            'registration_data' => $validated,
            'registration_role' => 'student'
        ]);

        // Redirect to password creation page
        return redirect()->route('account.create')
            ->with('success', 'Please create your password to complete registration.');
    }

    // ============================================================================
    // INSTRUCTOR REGISTRATION - SESSION-BASED
    // ============================================================================

    /**
     * Display the instructor registration form
     */
    public function showInstructorRegistrationForm()
    {
        $specializations = DB::table('specialization')
            ->where('is_active', true)
            ->orderBy('specialization_name')
            ->get();

        return view('auth.register.instructor', compact('specializations'));
    }

    /**
     * Process instructor registration - STORES IN SESSION ONLY
     * NO DATABASE INSERTION UNTIL PASSWORD CREATION
     */
    public function registerInstructor(Request $request)
    {
        // Validate ALL data including email uniqueness
        $validated = $request->validate([
            // Account - Email uniqueness check BEFORE storing
            'user_email' => 'required|email|unique:user_account,user_email',
            
            // Personal Info
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'phone' => 'required|regex:/^[0-9]{11}$/',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:Male,Female,Other,Prefer not to say',
            'nationality' => 'nullable|string|max:100',
            
            // Address (ALL REQUIRED)
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            
            // Emergency Contact (ALL REQUIRED)
            'emergency_contact_name' => 'required|string|max:100',
            'emergency_contact_relationship' => 'required|string|max:100',
            'emergency_contact_phone' => 'required|regex:/^[0-9]{11}$/',
            
            // Professional
            'education_level' => 'required|string|max:100',
            'music_degree' => 'nullable|string|max:200',
            'years_of_experience' => 'required|integer|min:0',
            'languages_spoken' => 'nullable|string|max:255',
            'teaching_style' => 'nullable|string',
            'bio' => 'nullable|string',
            'certifications' => 'nullable|array',
            'certifications.*' => 'nullable|string|max:255',
            
            // Specializations
            'specializations' => 'required|array|min:1',
            'specializations.*' => 'exists:specialization,specialization_id',
            'primary_specialization' => ['required', Rule::in($request->input('specializations', []))],
        ], [
            'user_email.unique' => 'This email is already registered. Please use a different email or login.',
            'phone.regex' => 'Phone number must be exactly 11 digits.',
            'emergency_contact_phone.regex' => 'Emergency contact phone must be exactly 11 digits.',
            'specializations.required' => 'Please select at least one specialization.',
            'primary_specialization.in' => 'Primary specialization must be one of your selected specializations.',
        ]);

        // STORE ALL DATA IN SESSION - NOT DATABASE YET
        session([
            'registration_data' => $validated,
            'registration_role' => 'instructor'
        ]);

        // Redirect to password creation page
        return redirect()->route('account.create')
            ->with('success', 'Please create your password to complete registration.');
    }

    // ============================================================================
    // SALES STAFF REGISTRATION - SESSION-BASED
    // ============================================================================

    public function showSalesStaffRegistrationForm()
    {
        return view('auth.register.sales-staff');
    }

    public function registerSalesStaff(Request $request)
    {
        $validated = $request->validate([
            'user_email' => 'required|email|unique:user_account,user_email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|regex:/^[0-9]{11}$/',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
        ], [
            'user_email.unique' => 'This email is already registered.',
            'phone.regex' => 'Phone number must be exactly 11 digits.',
        ]);

        //STORE IN SESSION
        session([
            'registration_data' => $validated,
            'registration_role' => 'sales_staff'
        ]);

        return redirect()->route('account.create')
            ->with('success', 'Please create your password to complete registration.');
    }

    // ============================================================================
    // ALL-AROUND STAFF REGISTRATION - SESSION-BASED
    // ============================================================================

    public function showStaffRegistrationForm()
    {
        return view('auth.register.staff');
    }

    public function registerStaff(Request $request)
    {
        $validated = $request->validate([
            'user_email' => 'required|email|unique:user_account,user_email',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone' => 'required|regex:/^[0-9]{11}$/',
            'middle_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
        ], [
            'user_email.unique' => 'This email is already registered.',
            'phone.regex' => 'Phone number must be exactly 11 digits.',
        ]);

        //STORE IN SESSION
        session([
            'registration_data' => $validated,
            'registration_role' => 'all_around_staff'
        ]);

        return redirect()->route('account.create')
            ->with('success', 'Please create your password to complete registration.');
    }

    // ============================================================================
    // PASSWORD SETUP (CREATE ACCOUNT) - THIS IS WHERE DATABASE INSERTION HAPPENS
    // ============================================================================

    /**
     * Show the create account (password setup) form
     * Retrieves registration data from session
     */
    public function showCreateAccountForm()
    {
        // Get registration data from session
        $registrationData = session('registration_data');
        $role = session('registration_role');
        
        // Validate session data exists
        if (!$registrationData || !$role) {
            return redirect()->route('register')
                ->withErrors(['error' => 'Registration session expired. Please start registration again.']);
        }

        $email = $registrationData['user_email'];

        // Double-check email is still available (in case session was old)
        $existingUser = UserAccount::where('user_email', $email)->first();
        if ($existingUser) {
            // Clear invalid session
            session()->forget(['registration_data', 'registration_role']);
            
            return redirect()->route('register')
                ->withErrors(['error' => 'This email is already registered. Please login or use a different email.']);
        }

        return view('auth.create-account', compact('email', 'role'));
    }

    /**
     * Process password setup and CREATE ALL DATABASE RECORDS
     * THIS IS WHERE EVERYTHING GETS SAVED TO DATABASE
     */
    public function processCreateAccount(Request $request)
    {
        // Validate password
        $passwordValidated = $request->validate([
            'user_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Get registration data from session
        $registrationData = session('registration_data');
        $role = session('registration_role');

        // Validate session still exists
        if (!$registrationData || !$role) {
            return redirect()->route('register')
                ->withErrors(['error' => 'Registration session expired. Please start again.']);
        }

        // Extract email from session data
        $email = $registrationData['user_email'];

        // Final email uniqueness check before insertion
        if (UserAccount::where('user_email', $email)->exists()) {
            session()->forget(['registration_data', 'registration_role']);
            return redirect()->route('register')
                ->withErrors(['error' => 'This email was just registered by someone else. Please use a different email.']);
        }

        // ============================================================================
        // BEGIN TRANSACTION - SAVE EVERYTHING TO DATABASE
        // ============================================================================
        DB::beginTransaction();

        try {
            // Step 1: Create user_account with password
            $user = UserAccount::create([
                'user_email' => $email,
                'user_password' => $passwordValidated['user_password'], // Will be hashed by mutator
                'is_super_admin' => false,
            ]);

            // Step 2: Create role-specific record based on registration type
            switch ($role) {
                case 'student':
                    $this->createStudentRecord($user->user_id, $registrationData);
                    $dashboardRoute = 'student.dashboard';
                    break;

                case 'instructor':
                    $this->createInstructorRecord($user->user_id, $registrationData);
                    $dashboardRoute = 'instructor.dashboard';
                    break;

                case 'sales_staff':
                    $this->createSalesStaffRecord($user->user_id, $registrationData);
                    $dashboardRoute = 'sales.dashboard';
                    break;

                case 'all_around_staff':
                    $this->createAllAroundStaffRecord($user->user_id, $registrationData);
                    $dashboardRoute = 'staff.dashboard';
                    break;

                default:
                    throw new \Exception('Invalid role type');
            }
            DB::commit();

            // Clear registration session data
            session()->forget(['registration_data', 'registration_role']);

            // Auto-login the user
            Auth::login($user);

            // Redirect to appropriate dashboard
            return redirect()->route($dashboardRoute)
                ->with('success', 'Welcome! Your account has been created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Account creation failed: ' . $e->getMessage());

            return back()
                ->withErrors(['error' => 'Account creation failed: ' . $e->getMessage()])
                ->withInput();
        }
    }

    // ============================================================================
    // HELPER METHODS - CREATE ROLE-SPECIFIC RECORDS
    // ============================================================================

    /**
     * Create student record in database
     */
    private function createStudentRecord($userId, $data)
    {
        $activeStatusId = DB::table('student_status')
            ->where('status_name', 'Active')
            ->value('status_id');

        if (!$activeStatusId) {
            throw new \Exception('Active student status not found in database.');
        }

        DB::table('student')->insert([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'suffix' => $data['suffix'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['user_email'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'instrument_id' => $data['instrument_id'] ?? null,
            'skill_level' => $data['skill_level'] ?? null,
            'music_goals' => $data['music_goals'] ?? null,
            'student_status_id' => $activeStatusId,
            'enrollment_date' => now()->format('Y-m-d'),
            'country' => 'Philippines',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create instructor record with specializations in database
     */
    private function createInstructorRecord($userId, $data)
    {
        // Insert instructor record
        DB::table('instructor')->insert([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'suffix' => $data['suffix'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['user_email'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'nationality' => $data['nationality'] ?? null,
            
            // Address
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'],
            'province' => $data['province'],
            'postal_code' => $data['postal_code'],
            'country' => $data['country'],
            
            // Emergency contact
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_relationship' => $data['emergency_contact_relationship'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
            
            // Professional
            'years_of_experience' => $data['years_of_experience'],
            'education_level' => $data['education_level'],
            'music_degree' => $data['music_degree'] ?? null,
            'languages_spoken' => $data['languages_spoken'] ?? null,
            'teaching_style' => $data['teaching_style'] ?? null,
            'bio' => $data['bio'] ?? null,
            
            'hire_date' => now(),
            'employment_status' => 'active',
            'is_available' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get the instructor_id we just created
        $instructorId = DB::table('instructor')
            ->where('user_id', $userId)
            ->value('instructor_id');

        if (!$instructorId) {
            throw new \Exception('Failed to retrieve instructor_id after creation.');
        }

        // Insert specializations
        foreach ($data['specializations'] as $specializationId) {
            DB::table('instructor_specialization')->insert([
                'instructor_id' => $instructorId,
                'specialization_id' => $specializationId,
                'is_primary' => ($specializationId == $data['primary_specialization']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Create sales staff record in database
     */
    private function createSalesStaffRecord($userId, $data)
    {
        DB::table('sales_staff')->insert([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['user_email'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'hire_date' => now(),
            'employment_status' => 'active',
            'country' => 'Philippines',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create all-around staff record in database
     */
    private function createAllAroundStaffRecord($userId, $data)
    {
        DB::table('all_around_staff')->insert([
            'user_id' => $userId,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['user_email'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'hire_date' => now(),
            'employment_status' => 'active',
            'country' => 'Philippines',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}