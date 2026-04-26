import 'package:flutter/material.dart';

// ==================== BASE USER MODELS ====================

class User {
  final String userId;
  final String name;
  final String email;
  final String role;
  final String? program;
  final int? yearLevel;
  final String enrollmentStatus;
  final String? contactNumber;
  final String? address;
  final String? studentType;
  final int? totalUnits;
  final String? enrollmentDate;

  User({
    required this.userId,
    required this.name,
    required this.email,
    required this.role,
    this.program,
    this.yearLevel,
    this.enrollmentStatus = 'enrolled',
    this.contactNumber,
    this.address,
    this.studentType,
    this.totalUnits,
    this.enrollmentDate,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      userId: json['user_id']?.toString() ?? '',
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      role: json['role'] ?? 'student',
      program: json['program'],
      yearLevel: json['year_level'] != null ? int.tryParse(json['year_level'].toString()) : null,
      enrollmentStatus: json['enrollment_status'] ?? 'enrolled',
      contactNumber: json['contact_number'] ?? json['number'],
      address: json['address'],
      studentType: json['student_type'] ?? 'regular',
      totalUnits: json['total_units'] != null ? int.tryParse(json['total_units'].toString()) : null,
      enrollmentDate: json['enrollment_date'],
    );
  }
}

class StudentInfo {
  final String? program;
  final int? yearLevel;
  final String enrollmentStatus;
  final String? contactNumber;
  final String? address;
  final String? studentType;
  final int? totalUnits;
  final String? enrollmentDate;

  StudentInfo({
    this.program,
    this.yearLevel,
    this.enrollmentStatus = 'enrolled',
    this.contactNumber,
    this.address,
    this.studentType,
    this.totalUnits,
    this.enrollmentDate,
  });

  factory StudentInfo.fromJson(Map<String, dynamic> json) {
    return StudentInfo(
      program: json['program'],
      yearLevel: json['year_level'] != null ? int.tryParse(json['year_level'].toString()) : null,
      enrollmentStatus: json['enrollment_status'] ?? 'enrolled',
      contactNumber: json['contact_number'] ?? json['number'],
      address: json['address'],
      studentType: json['student_type'] ?? 'regular',
      totalUnits: json['total_units'] != null ? int.tryParse(json['total_units'].toString()) : null,
      enrollmentDate: json['enrollment_date'],
    );
  }
}

// ==================== PAYMENT MODELS ====================

class PaymentStats {
  final int totalPayments;
  final int unpaidCount;
  final int paidCount;
  final int partialCount;
  final double totalDue;
  final double totalPaid;
  final double totalPartial;

  PaymentStats({
    required this.totalPayments,
    required this.unpaidCount,
    required this.paidCount,
    required this.partialCount,
    required this.totalDue,
    required this.totalPaid,
    required this.totalPartial,
  });

  factory PaymentStats.fromJson(Map<String, dynamic> json) {
    return PaymentStats(
      totalPayments: json['total_payments'] != null ? int.tryParse(json['total_payments'].toString()) ?? 0 : 0,
      unpaidCount: json['unpaid_count'] != null ? int.tryParse(json['unpaid_count'].toString()) ?? 0 : 0,
      paidCount: json['paid_count'] != null ? int.tryParse(json['paid_count'].toString()) ?? 0 : 0,
      partialCount: json['partial_count'] != null ? int.tryParse(json['partial_count'].toString()) ?? 0 : 0,
      totalDue: json['total_due'] != null ? double.tryParse(json['total_due'].toString()) ?? 0.0 : 0.0,
      totalPaid: json['total_paid'] != null ? double.tryParse(json['total_paid'].toString()) ?? 0.0 : 0.0,
      totalPartial: json['total_partial'] != null ? double.tryParse(json['total_partial'].toString()) ?? 0.0 : 0.0,
    );
  }
}

class Payment {
  final String id;
  final String permitNumber;
  final double amount;
  final String description;
  final String paymentStatus;
  final DateTime issuedDate;
  final DateTime? dueDate;
  final String? receiptNumber;
  final String? issuedBy;
  final String? paymentType;
  final String? paymentCategory;
  final int? units;
  final double? remainingBalance;
  final String? schoolYear;
  final String? amountText;

  Payment({
    required this.id,
    required this.permitNumber,
    required this.amount,
    required this.description,
    required this.paymentStatus,
    required this.issuedDate,
    this.dueDate,
    this.receiptNumber,
    this.issuedBy,
    this.paymentType,
    this.paymentCategory,
    this.units,
    this.remainingBalance,
    this.schoolYear,
    this.amountText,
  });

  factory Payment.fromJson(Map<String, dynamic> json) {
    return Payment(
      id: json['id']?.toString() ?? '',
      permitNumber: json['permit_number'] ?? '',
      amount: json['amount'] != null ? double.tryParse(json['amount'].toString()) ?? 0.0 : 0.0,
      description: json['description'] ?? '',
      paymentStatus: json['payment_status'] ?? 'unpaid',
      issuedDate: _parseDate(json['issued_date'] ?? ''),
      dueDate: _parseDate(json['due_date']),
      receiptNumber: json['receipt_number'],
      issuedBy: json['issued_by'],
      paymentType: json['payment_type'] ?? 'regular',
      paymentCategory: json['payment_category'] ?? 'other',
      units: json['units'] != null ? int.tryParse(json['units'].toString()) : null,
      remainingBalance: json['remaining_balance'] != null ? double.tryParse(json['remaining_balance'].toString()) : null,
      schoolYear: json['school_year'],
      amountText: json['amount_text'],
    );
  }

  static DateTime _parseDate(dynamic date) {
    if (date == null) return DateTime.now();
    try {
      if (date is String) return DateTime.parse(date);
      return DateTime.now();
    } catch (e) {
      return DateTime.now();
    }
  }
}

// ==================== ACADEMIC MODELS ====================

class Subject {
  final String subjectCode;
  final String subjectName;
  final int units;
  final String? subjectDescription;
  final String? sectionCode;
  final String? program;
  final String? schedule;
  final String? room;

  Subject({
    required this.subjectCode,
    required this.subjectName,
    required this.units,
    this.subjectDescription,
    this.sectionCode,
    this.program,
    this.schedule,
    this.room,
  });

  factory Subject.fromJson(Map<String, dynamic> json) {
    return Subject(
      subjectCode: json['subject_code'] ?? '',
      subjectName: json['subject_name'] ?? '',
      units: json['units'] != null ? int.tryParse(json['units'].toString()) ?? 0 : 0,
      subjectDescription: json['subject_description'],
      sectionCode: json['section_code'],
      program: json['program'],
      schedule: json['schedule'],
      room: json['room'],
    );
  }
}

class AcademicSummary {
  final int totalSubjects;
  final int totalUnits;

  AcademicSummary({
    required this.totalSubjects,
    required this.totalUnits,
  });

  factory AcademicSummary.fromJson(Map<String, dynamic> json) {
    return AcademicSummary(
      totalSubjects: json['total_subjects'] != null ? int.tryParse(json['total_subjects'].toString()) ?? 0 : 0,
      totalUnits: json['total_units'] != null ? int.tryParse(json['total_units'].toString()) ?? 0 : 0,
    );
  }
}

class BalanceSummary {
  final double totalUnpaid;
  final double totalPartialPaid;

  BalanceSummary({
    required this.totalUnpaid,
    required this.totalPartialPaid,
  });

  factory BalanceSummary.fromJson(Map<String, dynamic> json) {
    return BalanceSummary(
      totalUnpaid: json['total_unpaid'] != null ? double.tryParse(json['total_unpaid'].toString()) ?? 0.0 : 0.0,
      totalPartialPaid: json['total_partial_paid'] != null ? double.tryParse(json['total_partial_paid'].toString()) ?? 0.0 : 0.0,
    );
  }
}

// ==================== ACADEMIC PROGRESS MODELS ====================

class AcademicProgress {
  final int totalSubjects;
  final int completedCount;
  final int inProgressCount;
  final double? averageGrade;
  final List<CurriculumYear> curriculum;

  AcademicProgress({
    required this.totalSubjects,
    required this.completedCount,
    required this.inProgressCount,
    required this.averageGrade,
    required this.curriculum,
  });

  factory AcademicProgress.fromJson(Map<String, dynamic> json) {
    List<CurriculumYear> curriculum = [];
    if (json['curriculum'] != null && json['curriculum'] is List) {
      curriculum = (json['curriculum'] as List).map((item) => CurriculumYear.fromJson(item)).toList();
    }
    return AcademicProgress(
      totalSubjects: json['total_subjects'] ?? 0,
      completedCount: json['completed_count'] ?? 0,
      inProgressCount: json['in_progress_count'] ?? 0,
      averageGrade: json['avg_grade'] != null ? double.tryParse(json['avg_grade'].toString()) : null,
      curriculum: curriculum,
    );
  }
}

class CurriculumYear {
  final int year;
  final String status;
  final List<SemesterData> semesters;

  CurriculumYear({
    required this.year,
    required this.status,
    required this.semesters,
  });

  factory CurriculumYear.fromJson(Map<String, dynamic> json) {
    List<SemesterData> semesters = [];
    if (json['semesters'] != null && json['semesters'] is List) {
      semesters = (json['semesters'] as List).map((item) => SemesterData.fromJson(item)).toList();
    }
    return CurriculumYear(
      year: json['year'] ?? 0,
      status: json['status'] ?? 'upcoming',
      semesters: semesters,
    );
  }
}

class SemesterData {
  final String semester;
  final int totalUnits;
  final List<CurriculumSubject> subjects;

  SemesterData({
    required this.semester,
    required this.totalUnits,
    required this.subjects,
  });

  factory SemesterData.fromJson(Map<String, dynamic> json) {
    List<CurriculumSubject> subjects = [];
    if (json['subjects'] != null && json['subjects'] is List) {
      subjects = (json['subjects'] as List).map((item) => CurriculumSubject.fromJson(item)).toList();
    }
    return SemesterData(
      semester: json['semester'] ?? '',
      totalUnits: json['total_units'] ?? 0,
      subjects: subjects,
    );
  }
}

class CurriculumSubject {
  final String subjectCode;
  final String subjectName;
  final int units;
  final double? grade;
  final String? dateReceived;
  final String status;
  final bool isTaking;
  final bool isExtra;

  CurriculumSubject({
    required this.subjectCode,
    required this.subjectName,
    required this.units,
    this.grade,
    this.dateReceived,
    required this.status,
    required this.isTaking,
    this.isExtra = false,
  });

  factory CurriculumSubject.fromJson(Map<String, dynamic> json) {
    return CurriculumSubject(
      subjectCode: json['subject_code'] ?? '',
      subjectName: json['subject_name'] ?? '',
      units: json['units'] ?? 0,
      grade: json['grade'] != null ? double.tryParse(json['grade'].toString()) : null,
      dateReceived: json['date_received'],
      status: json['status'] ?? 'not_taken',
      isTaking: json['is_taking'] ?? false,
      isExtra: json['is_extra'] ?? false,
    );
  }

  String getGradeLetter() {
    if (grade == null) return '—';
    if (grade! <= 1.5) return 'Excellent';
    if (grade! <= 2.0) return 'Good';
    if (grade! <= 2.75) return 'Average';
    if (grade! <= 3.0) return 'Pass';
    return 'Fail';
  }

  Color getGradeColor() {
    if (grade == null) return Colors.grey;
    if (grade! <= 1.5) return Colors.green;
    if (grade! <= 2.0) return Colors.blue;
    if (grade! <= 2.75) return Colors.orange;
    if (grade! <= 3.0) return Colors.amber;
    return Colors.red;
  }
}

// ==================== SCHEDULE MODELS ====================

class ClassSchedule {
  final String subjectCode;
  final String subjectName;
  final String dayOfWeek;
  final String startTime;
  final String endTime;
  final String? room;
  final String sectionCode;

  ClassSchedule({
    required this.subjectCode,
    required this.subjectName,
    required this.dayOfWeek,
    required this.startTime,
    required this.endTime,
    this.room,
    required this.sectionCode,
  });

  factory ClassSchedule.fromJson(Map<String, dynamic> json) {
    return ClassSchedule(
      subjectCode: json['subject_code'] ?? '',
      subjectName: json['subject_name'] ?? '',
      dayOfWeek: json['day_of_week'] ?? '',
      startTime: json['start_time'] ?? '',
      endTime: json['end_time'] ?? '',
      room: json['room'] ?? 'TBA',
      sectionCode: json['section_code'] ?? '',
    );
  }

  String getTimeRange() {
    return '$startTime - $endTime';
  }
}

// ==================== ACTIVITY LOG MODEL ====================

class ActivityLog {
  final String action;
  final String description;
  final String createdAt;
  final String? userName;

  ActivityLog({
    required this.action,
    required this.description,
    required this.createdAt,
    this.userName,
  });

  factory ActivityLog.fromJson(Map<String, dynamic> json) {
    return ActivityLog(
      action: json['action'] ?? '',
      description: json['description'] ?? '',
      createdAt: json['created_at'] ?? '',
      userName: json['name'],
    );
  }
}

// ==================== DASHBOARD RESPONSE MODEL ====================

class DashboardData {
  final User user;
  final StudentInfo studentInfo;
  final PaymentStats paymentStats;
  final List<Payment> recentPayments;
  final List<Subject> currentSubjects;
  final AcademicSummary academicSummary;
  final BalanceSummary balanceSummary;
  final List<ActivityLog> recentActivities;

  DashboardData({
    required this.user,
    required this.studentInfo,
    required this.paymentStats,
    required this.recentPayments,
    required this.currentSubjects,
    required this.academicSummary,
    required this.balanceSummary,
    required this.recentActivities,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    List<Payment> recentPayments = [];
    if (json['recent_payments'] != null && json['recent_payments'] is List) {
      recentPayments = (json['recent_payments'] as List)
          .map((item) => Payment.fromJson(item as Map<String, dynamic>))
          .toList();
    }

    List<Subject> currentSubjects = [];
    if (json['current_subjects'] != null && json['current_subjects'] is List) {
      currentSubjects = (json['current_subjects'] as List)
          .map((item) => Subject.fromJson(item as Map<String, dynamic>))
          .toList();
    }

    List<ActivityLog> recentActivities = [];
    if (json['recent_activities'] != null && json['recent_activities'] is List) {
      recentActivities = (json['recent_activities'] as List)
          .map((item) => ActivityLog.fromJson(item as Map<String, dynamic>))
          .toList();
    }

    return DashboardData(
      user: User.fromJson(json['user'] ?? {}),
      studentInfo: StudentInfo.fromJson(json['student_info'] ?? {}),
      paymentStats: PaymentStats.fromJson(json['payment_stats'] ?? {}),
      recentPayments: recentPayments,
      currentSubjects: currentSubjects,
      academicSummary: AcademicSummary.fromJson(json['academic_summary'] ?? {}),
      balanceSummary: BalanceSummary.fromJson(json['balance_summary'] ?? {}),
      recentActivities: recentActivities,
    );
  }
}