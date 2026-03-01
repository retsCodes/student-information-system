<?php
require_once '../init.php';

class CourseManagement {
    private $pdo;
    
    public function __construct($pdo = null) {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    // =======================================================
    // COURSE MANAGEMENT
    // =======================================================
    
    public function createCourse($data) {
        $sql = "INSERT INTO courses (course_code, course_name, description, total_units, duration_years) 
                VALUES (:code, :name, :desc, :units, :years)";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':code' => $data['course_code'],
                ':name' => $data['course_name'],
                ':desc' => $data['description'],
                ':units' => $data['total_units'],
                ':years' => $data['duration_years']
            ]);
            
            $courseId = $this->pdo->lastInsertId();
            $this->logActivity($_SESSION['user_id'], "create_course", "Created course: {$data['course_name']}");
            
            return ['success' => true, 'course_id' => $courseId];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateCourse($courseId, $data) {
        $sql = "UPDATE courses SET course_name = :name, description = :desc, 
                total_units = :units, duration_years = :years, status = :status 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':name' => $data['course_name'],
                ':desc' => $data['description'],
                ':units' => $data['total_units'],
                ':years' => $data['duration_years'],
                ':status' => $data['status'],
                ':id' => $courseId
            ]);
            
            $this->logActivity($_SESSION['user_id'], "update_course", "Updated course ID: $courseId");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getAllCourses() {
        $sql = "SELECT c.*, COUNT(cc.id) as subject_count 
                FROM courses c 
                LEFT JOIN course_curriculum cc ON c.id = cc.course_id 
                GROUP BY c.id 
                ORDER BY c.course_code";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getActiveCourses() {
        $sql = "SELECT c.* FROM courses c WHERE c.status = 'active' ORDER BY c.course_code";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCourseById($id) {
        $sql = "SELECT * FROM courses WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function deleteCourse($id) {
        // Check if course has students enrolled
        $checkSql = "SELECT COUNT(*) as count FROM student_course_enrollment WHERE course_id = ? AND status = 'active'";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            return ['success' => false, 'error' => 'Cannot delete course with active students'];
        }
        
        $sql = "UPDATE courses SET status = 'inactive' WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([$id]);
            $this->logActivity($_SESSION['user_id'], "delete_course", "Deactivated course ID: $id");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =======================================================
    // CURRICULUM MANAGEMENT
    // =======================================================
    
    public function addSubjectToCurriculum($data) {
        // Check if subject already exists
        $checkSql = "SELECT id FROM course_curriculum 
                    WHERE course_id = ? AND subject_id = ? AND year_level = ? AND semester = ?";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([
            $data['course_id'],
            $data['subject_id'],
            $data['year_level'],
            $data['semester']
        ]);
        
        if ($checkStmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Subject already exists in curriculum'];
        }
        
        $sql = "INSERT INTO course_curriculum (course_id, subject_id, year_level, semester, 
                is_required, suggested_units, prerequisites, order_index) 
                VALUES (:course_id, :subject_id, :year_level, :semester, 
                :is_required, :suggested_units, :prerequisites, :order_index)";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':course_id' => $data['course_id'],
                ':subject_id' => $data['subject_id'],
                ':year_level' => $data['year_level'],
                ':semester' => $data['semester'],
                ':is_required' => $data['is_required'],
                ':suggested_units' => $data['suggested_units'],
                ':prerequisites' => $data['prerequisites'],
                ':order_index' => $data['order_index']
            ]);
            
            $this->logActivity($_SESSION['user_id'], "add_curriculum_subject", 
                "Added subject to curriculum for course ID: {$data['course_id']}");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getCourseCurriculum($courseId) {
        $sql = "SELECT cc.*, s.subject_code, s.subject_name, s.units, 
                       c.course_name, c.course_code
                FROM course_curriculum cc
                JOIN subjects s ON cc.subject_id = s.id
                JOIN courses c ON cc.course_id = c.id
                WHERE cc.course_id = ?
                ORDER BY cc.year_level, 
                         FIELD(cc.semester, '1st', '2nd', 'summer'), 
                         cc.order_index";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getCurriculumByYearSemester($courseId, $yearLevel, $semester = null) {
        $sql = "SELECT cc.*, s.subject_code, s.subject_name, s.units
                FROM course_curriculum cc
                JOIN subjects s ON cc.subject_id = s.id
                WHERE cc.course_id = ? AND cc.year_level = ?";
        
        $params = [$courseId, $yearLevel];
        
        if ($semester) {
            $sql .= " AND cc.semester = ?";
            $params[] = $semester;
        }
        
        $sql .= " ORDER BY cc.order_index";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function removeSubjectFromCurriculum($curriculumId) {
        $sql = "DELETE FROM course_curriculum WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([$curriculumId]);
            $this->logActivity($_SESSION['user_id'], "remove_curriculum_subject", 
                "Removed subject from curriculum ID: $curriculumId");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function updateCurriculumSubject($curriculumId, $data) {
        $sql = "UPDATE course_curriculum 
                SET year_level = :year_level, semester = :semester, 
                    is_required = :is_required, order_index = :order_index,
                    prerequisites = :prerequisites
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':year_level' => $data['year_level'],
                ':semester' => $data['semester'],
                ':is_required' => $data['is_required'],
                ':order_index' => $data['order_index'],
                ':prerequisites' => $data['prerequisites'],
                ':id' => $curriculumId
            ]);
            
            $this->logActivity($_SESSION['user_id'], "update_curriculum_subject", 
                "Updated curriculum subject ID: $curriculumId");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =======================================================
    // STUDENT COURSE ASSIGNMENT
    // =======================================================
    
    public function assignCourseToStudent($studentId, $courseId, $enrollmentDate = null) {
        if (!$enrollmentDate) {
            $enrollmentDate = date('Y-m-d');
        }
        
        // Calculate expected graduation
        $expectedGraduation = date('Y-m-d', strtotime($enrollmentDate . ' + 4 years'));
        
        // Check if already enrolled
        $checkSql = "SELECT id FROM student_course_enrollment 
                    WHERE student_id = ? AND course_id = ? AND status = 'active'";
        $checkStmt = $this->pdo->prepare($checkSql);
        $checkStmt->execute([$studentId, $courseId]);
        
        if ($checkStmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'Student already enrolled in this course'];
        }
        
        $sql = "INSERT INTO student_course_enrollment 
                (student_id, course_id, enrollment_date, expected_graduation, current_year_level) 
                VALUES (?, ?, ?, ?, 1)";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([$studentId, $courseId, $enrollmentDate, $expectedGraduation]);
            
            // Update students_info
            $updateSql = "UPDATE students_info 
                         SET course_id = ?, 
                             program = (SELECT course_name FROM courses WHERE id = ?),
                             curriculum_year = 1
                         WHERE user_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$courseId, $courseId, $studentId]);
            
            $this->logActivity($_SESSION['user_id'], "assign_course", 
                "Assigned course to student: $studentId");
            
            // Generate estimated subjects
            $this->generateEstimatedSubjects($studentId, $courseId);
            
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function generateEstimatedSubjects($studentId, $courseId) {
        // Get curriculum
        $curriculum = $this->getCourseCurriculum($courseId);
        
        // Clear existing pending adjustments
        $clearSql = "DELETE FROM student_curriculum_adjustments 
                    WHERE student_id = ? AND status = 'pending'";
        $clearStmt = $this->pdo->prepare($clearSql);
        $clearStmt->execute([$studentId]);
        
        // Insert estimated subjects
        foreach ($curriculum as $subject) {
            $sql = "INSERT INTO student_curriculum_adjustments 
                    (student_id, subject_id, original_year_level, original_semester, 
                     adjusted_year_level, adjusted_semester, adjusted_by, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $studentId,
                $subject['subject_id'],
                $subject['year_level'],
                $subject['semester'],
                $subject['year_level'],
                $subject['semester'],
                $_SESSION['user_id']
            ]);
        }
        
        return ['success' => true, 'count' => count($curriculum)];
    }
    
    public function getStudentCourseInfo($studentId) {
        $sql = "SELECT sce.*, c.course_code, c.course_name
                FROM student_course_enrollment sce
                JOIN courses c ON sce.course_id = c.id
                WHERE sce.student_id = ? AND sce.status = 'active'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getEstimatedSubjects($studentId) {
        $sql = "SELECT sca.*, s.subject_code, s.subject_name, s.units, 
                       c.course_name, u.name as adjusted_by_name
                FROM student_curriculum_adjustments sca
                JOIN subjects s ON sca.subject_id = s.id
                LEFT JOIN student_course_enrollment sce ON sca.student_id = sce.student_id
                LEFT JOIN courses c ON sce.course_id = c.id
                LEFT JOIN users u ON sca.adjusted_by = u.user_id
                WHERE sca.student_id = ? 
                AND sca.status = 'pending'
                ORDER BY sca.adjusted_year_level, 
                         FIELD(sca.adjusted_semester, '1st', '2nd', 'summer')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function adjustStudentSubject($data) {
        $sql = "UPDATE student_curriculum_adjustments 
                SET adjusted_year_level = :year_level, 
                    adjusted_semester = :semester,
                    adjustment_reason = :reason,
                    adjusted_by = :adjusted_by,
                    adjustment_date = NOW()
                WHERE student_id = :student_id 
                AND subject_id = :subject_id 
                AND status = 'pending'";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':year_level' => $data['adjusted_year_level'],
                ':semester' => $data['adjusted_semester'],
                ':reason' => $data['adjustment_reason'],
                ':adjusted_by' => $_SESSION['user_id'],
                ':student_id' => $data['student_id'],
                ':subject_id' => $data['subject_id']
            ]);
            
            $this->logActivity($_SESSION['user_id'], "adjust_subject", 
                "Adjusted subject for student: {$data['student_id']}");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function approveAdjustedSubjects($studentId) {
        // Get student's course
        $courseInfo = $this->getStudentCourseInfo($studentId);
        if (!$courseInfo) {
            return ['success' => false, 'error' => 'Student not enrolled in any course'];
        }
        
        // Get approved adjustments for current year
        $sql = "SELECT sca.*, s.subject_code 
                FROM student_curriculum_adjustments sca
                JOIN subjects s ON sca.subject_id = s.id
                WHERE sca.student_id = ? 
                AND sca.status = 'pending'
                AND sca.adjusted_year_level = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$studentId, $courseInfo['current_year_level']]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subjectsAssigned = 0;
        
        foreach ($subjects as $subject) {
            // Assign to student_subjects table
            $assignSql = "INSERT INTO student_subjects (student_id, subject_id) 
                         VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id)";
            $assignStmt = $this->pdo->prepare($assignSql);
            $assignStmt->execute([$studentId, $subject['subject_id']]);
            $subjectsAssigned++;
            
            // Mark as approved
            $updateSql = "UPDATE student_curriculum_adjustments 
                         SET status = 'approved' 
                         WHERE id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$subject['id']]);
        }
        
        $this->logActivity($_SESSION['user_id'], "approve_subjects", 
            "Approved subjects for student: $studentId");
        
        return ['success' => true, 'assigned' => $subjectsAssigned];
    }
    
    // =======================================================
    // STUDENT MANAGEMENT
    // =======================================================
    
    public function getStudentsByCourse($courseId, $yearLevel = null) {
        $sql = "SELECT si.*, u.name, u.email, sce.enrollment_date, sce.current_year_level
                FROM students_info si
                JOIN users u ON si.user_id = u.user_id
                JOIN student_course_enrollment sce ON si.user_id = sce.student_id
                WHERE sce.course_id = ? 
                AND si.enrollment_status = 'enrolled'
                AND sce.status = 'active'";
        
        $params = [$courseId];
        
        if ($yearLevel) {
            $sql .= " AND sce.current_year_level = ?";
            $params[] = $yearLevel;
        }
        
        $sql .= " ORDER BY u.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStudentYearLevel($studentId, $yearLevel) {
        $sql = "UPDATE student_course_enrollment 
                SET current_year_level = ? 
                WHERE student_id = ? AND status = 'active'";
        $stmt = $this->pdo->prepare($sql);
        
        try {
            $stmt->execute([$yearLevel, $studentId]);
            
            // Update students_info
            $updateSql = "UPDATE students_info SET curriculum_year = ? WHERE user_id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$yearLevel, $studentId]);
            
            $this->logActivity($_SESSION['user_id'], "update_year_level", 
                "Updated year level for student: $studentId to $yearLevel");
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    // =======================================================
    // UTILITY FUNCTIONS
    // =======================================================
    
    public function getAvailableSubjects() {
        $sql = "SELECT id, subject_code, subject_name, units 
                FROM subjects 
                ORDER BY subject_code";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSubjectsNotInCurriculum($courseId) {
        $sql = "SELECT s.id, s.subject_code, s.subject_name, s.units
                FROM subjects s
                WHERE s.id NOT IN (
                    SELECT subject_id FROM course_curriculum WHERE course_id = ?
                )
                ORDER BY s.subject_code";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function searchStudents($searchTerm) {
        $sql = "SELECT u.user_id, u.name, u.email, si.student_type, 
                       c.course_name, sce.current_year_level
                FROM users u
                JOIN students_info si ON u.user_id = si.user_id
                LEFT JOIN student_course_enrollment sce ON u.user_id = sce.student_id AND sce.status = 'active'
                LEFT JOIN courses c ON sce.course_id = c.id
                WHERE u.role = 'student' 
                AND (u.name LIKE ? OR u.user_id LIKE ? OR u.email LIKE ?)
                LIMIT 20";
        
        $search = "%$searchTerm%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search, $search, $search]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStudentProgress($studentId) {
        $sql = "SELECT 
                COUNT(DISTINCT sca.subject_id) as total_subjects,
                SUM(CASE WHEN sca.status = 'approved' THEN 1 ELSE 0 END) as completed_subjects,
                sce.current_year_level,
                c.duration_years
                FROM student_course_enrollment sce
                JOIN courses c ON sce.course_id = c.id
                LEFT JOIN student_curriculum_adjustments sca ON sce.student_id = sca.student_id
                WHERE sce.student_id = ?
                GROUP BY sce.student_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function logActivity($userId, $action, $description) {
        $logId = uniqid('log_');
        $sql = "INSERT INTO activity_logs (log_id, user_id, action, description) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$logId, $userId, $action, $description]);
    }
}
?>