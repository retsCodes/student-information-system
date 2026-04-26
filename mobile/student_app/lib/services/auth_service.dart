import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models.dart';

class AuthService {
  static const String _baseUrl = 'https://rets.free.nf/students_information_system/mobile_api.php';

  // Complete browser headers to bypass InfinityFree protection
  static final Map<String, String> _headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    'Accept-Language': 'en-US,en;q=0.9',
    'Accept-Encoding': 'gzip, deflate, br',
    'Connection': 'keep-alive',
    'Cache-Control': 'max-age=0',
    'Sec-Ch-Ua': '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
    'Sec-Ch-Ua-Mobile': '?0',
    'Sec-Ch-Ua-Platform': '"Windows"',
    'Sec-Fetch-Dest': 'document',
    'Sec-Fetch-Mode': 'navigate',
    'Sec-Fetch-Site': 'none',
    'Sec-Fetch-User': '?1',
    'Upgrade-Insecure-Requests': '1',
  };

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'user_data';
  static const String _userIdKey = 'user_id';

  // Test API connection
  Future<Map<String, dynamic>> testConnection() async {
    try {
      print('Testing connection to: $_baseUrl');

      final response = await http.get(
        Uri.parse('$_baseUrl?action=test'),
        headers: _headers,
      ).timeout(const Duration(seconds: 15));

      print('Response status: ${response.statusCode}');
      print('Response preview: ${response.body.substring(0, response.body.length > 200 ? 200 : response.body.length)}');

      // Check if we got the challenge page
      if (response.body.contains('aes.js') || response.body.contains('<html')) {
        return {
          'success': false,
          'message': 'Server protection active. Please contact administrator to whitelist API endpoints.',
        };
      }

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
      print('Attempting login for: $userId');

      final response = await http.post(
        Uri.parse(_baseUrl),
        headers: {
          ..._headers,
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: {
          'action': 'login',
          'user_id': userId,
          'password': password,
        },
      ).timeout(const Duration(seconds: 15));

      print('Response status: ${response.statusCode}');

      // Check for challenge page
      if (response.body.contains('aes.js') || response.body.contains('<html')) {
        return {
          'success': false,
          'message': 'Server protection active. Cannot login via app. Please use web browser.',
        };
      }

      final Map<String, dynamic> data = jsonDecode(response.body);

      if (data['success'] == true) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_tokenKey, data['data']['token'] ?? '');
        await prefs.setString(_userKey, jsonEncode(data['data']['user']));
        await prefs.setString(_userIdKey, userId);

        print('✅ Login successful for: $userId');

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

      final body = {
        'action': action,
        'user_id': userId,
        'token': token,
        ...?extraData,
      };

      final response = await http.post(
        Uri.parse(_baseUrl),
        headers: {
          ..._headers,
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: body,
      ).timeout(const Duration(seconds: 10));

      if (response.body.contains('aes.js') || response.body.contains('<html')) {
        return {
          'success': false,
          'message': 'Server protection active. Please refresh or try again later.',
        };
      }

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