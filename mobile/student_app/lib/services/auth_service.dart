import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models.dart';

class AuthService {
  // =======================================================
  // FOR LOCAL XAMPP DEVELOPMENT (Android Emulator)
  // =======================================================
  // Android Emulator uses 10.0.2.2 to access host machine's localhost
  static const String _baseUrl = 'http://10.0.2.2/students_information_system/mobile_api.php';

  // For local testing on physical device via USB, use your computer's IP:
  // static const String _baseUrl = 'http://192.168.1.100/students_information_system/mobile_api.php';

  // FOR PRODUCTION CLOUD (uncomment when deploying):
  // static const String _baseUrl = 'https://rets.free.nf/students_information_system/mobile_api.php';

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'user_data';
  static const String _userIdKey = 'user_id';

  // Test API connection
  Future<Map<String, dynamic>> testConnection() async {
    try {
      print('Testing connection to: $_baseUrl');

      final response = await http.get(
        Uri.parse('$_baseUrl?action=test'),
      ).timeout(const Duration(seconds: 10));

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      if (response.statusCode == 200) {
        return jsonDecode(response.body);
      } else {
        return {
          'success': false,
          'message': 'HTTP Error: ${response.statusCode}',
        };
      }
    } catch (e) {
      print('Connection error: $e');
      return {
        'success': false,
        'message': 'Connection error: $e',
      };
    }
  }

  // Login
  Future<Map<String, dynamic>> login(String userId, String password) async {
    try {
      print('Attempting login to: $_baseUrl');
      print('User ID: $userId');

      final response = await http.post(
        Uri.parse(_baseUrl),
        body: {
          'action': 'login',
          'user_id': userId,
          'password': password,
        },
      ).timeout(const Duration(seconds: 15));

      print('Response status: ${response.statusCode}');
      print('Response body: ${response.body}');

      final Map<String, dynamic> data = jsonDecode(response.body);

      if (data['success'] == true) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_tokenKey, data['data']['token'] ?? '');
        await prefs.setString(_userKey, jsonEncode(data['data']['user']));
        await prefs.setString(_userIdKey, userId);

        print('✅ Login successful');

        return {
          'success': true,
          'user': data['data']['user'],
          'token': data['data']['token'],
        };
      } else {
        print('❌ Login failed: ${data['message']}');
        return {
          'success': false,
          'message': data['message'] ?? 'Login failed',
        };
      }
    } catch (e) {
      print('💥 Login error: $e');
      return {
        'success': false,
        'message': 'Connection error: $e',
      };
    }
  }

  // Check login status
  Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    final userId = prefs.getString(_userIdKey);
    return token != null && userId != null;
  }

  // Logout
  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_userKey);
    await prefs.remove(_userIdKey);
  }

  // Get current user
  Future<User?> getCurrentUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString(_userKey);
    if (userJson != null) {
      try {
        return User.fromJson(jsonDecode(userJson));
      } catch (e) {
        return null;
      }
    }
    return null;
  }

  // Authenticated request helper
  Future<Map<String, dynamic>> _authenticatedRequest(String action, {Map<String, dynamic>? extraData}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getString(_userIdKey);
      final token = prefs.getString(_tokenKey);

      if (userId == null || token == null) {
        return {
          'success': false,
          'message': 'Not authenticated. Please login again.',
        };
      }

      final response = await http.post(
        Uri.parse(_baseUrl),
        body: {
          'action': action,
          'user_id': userId,
          'token': token,
          ...?extraData,
        },
      ).timeout(const Duration(seconds: 10));

      return jsonDecode(response.body);
    } catch (e) {
      return {
        'success': false,
        'message': 'Error: $e',
      };
    }
  }

  // Dashboard
  Future<Map<String, dynamic>> getDashboardData() async {
    return _authenticatedRequest('dashboard');
  }

  // Get all payments
  Future<Map<String, dynamic>> getPayments() async {
    return _authenticatedRequest('get_payments');
  }

  // Get schedule
  Future<Map<String, dynamic>> getSchedule() async {
    return _authenticatedRequest('get_schedule');
  }

  // Get full schedule with times
  Future<Map<String, dynamic>> getFullSchedule() async {
    return _authenticatedRequest('get_full_schedule');
  }

  // Get profile
  Future<Map<String, dynamic>> getProfile() async {
    return _authenticatedRequest('get_profile');
  }

  // Get academic progress
  Future<Map<String, dynamic>> getAcademicProgress() async {
    return _authenticatedRequest('get_academic_progress');
  }

  // Get recent activities
  Future<Map<String, dynamic>> getRecentActivities() async {
    return _authenticatedRequest('get_recent_activities');
  }
}