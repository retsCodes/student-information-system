import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models.dart';

class AuthService {
static const String _defaultBaseUrl = 'https://rets.free.nf/students_information_system/mobile_api.php';
  static String _baseUrl = _defaultBaseUrl;
  static bool _useAutoDiscovery = true;

  // Storage keys
  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'user_data';
  static const String _userIdKey = 'user_id';
  static const String _serverUrlKey = 'server_url';

  // ==================== CONNECTION & DISCOVERY ====================

  // Set custom server URL
  static Future<void> setBaseUrl(String url) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_serverUrlKey, url);
    _baseUrl = url;
    _useAutoDiscovery = false;
    print('✅ Server URL set to: $url');
  }

  // Get current server URL with auto-discovery
  static Future<String> getBaseUrl() async {
    if (!_useAutoDiscovery && _baseUrl != _defaultBaseUrl) {
      return _baseUrl;
    }

    final prefs = await SharedPreferences.getInstance();

    // Check for saved custom URL
    final savedUrl = prefs.getString(_serverUrlKey);
    if (savedUrl != null && savedUrl.isNotEmpty) {
      _baseUrl = savedUrl;
      _useAutoDiscovery = false;
      return _baseUrl;
    }

    // Try auto-discovery
    final discovered = await _discoverServer();
    if (discovered != null) {
      _baseUrl = discovered;
      print('✅ Auto-discovered server at: $_baseUrl');
      return _baseUrl;
    }

    // Fallback to default
    print('⚠️ Using default URL: $_defaultBaseUrl');
    return _defaultBaseUrl;
  }

  static Future<String?> _discoverServer() async {
    final possibleIps = [
      '192.168.1.100',
      '192.168.1.101',
      '192.168.1.50',
      '192.168.0.100',
      '192.168.0.101',
      '192.168.0.50',
      '10.0.0.100',
      '10.0.0.101',
      '172.16.0.100',
      '172.16.0.101',
    ];

    for (var ip in possibleIps) {
      final testUrl = 'http://$ip/students_information_system/mobile_api.php';
      if (await _testReachable(testUrl)) {
        return testUrl;
      }
    }
    return null;
  }

  static Future<bool> _testReachable(String url) async {
    try {
      final response = await http.get(
        Uri.parse('$url?action=test'),
      ).timeout(const Duration(seconds: 2));
      return response.statusCode == 200;
    } catch (e) {
      return false;
    }
  }

  // Test API connection
  Future<Map<String, dynamic>> testConnection() async {
    try {
      final baseUrl = await getBaseUrl();
      final response = await http.get(
        Uri.parse('$baseUrl?action=test'),
      ).timeout(const Duration(seconds: 5));

      return jsonDecode(response.body);
    } catch (e) {
      return {
        'success': false,
        'message': 'Connection error: $e'
      };
    }
  }

  // ==================== AUTHENTICATION ====================

  // Login
  Future<Map<String, dynamic>> login(String userId, String password) async {
    try {
      final baseUrl = await getBaseUrl();

      print('🔐 Attempting login to: $baseUrl');
      print('📱 User ID: $userId');

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'login',
          'user_id': userId,
          'password': password,
        },
      ).timeout(const Duration(seconds: 10));

      print('📡 Response status: ${response.statusCode}');
      final Map<String, dynamic> data = jsonDecode(response.body);
      print('📦 Response data: $data');

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
    final userJson = prefs.getString(_userKey);
    final userId = prefs.getString(_userIdKey);
    return token != null && userJson != null && userId != null;
  }

  // Get current user
  Future<User?> getCurrentUser() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString(_userKey);

    if (userJson != null) {
      try {
        final userData = jsonDecode(userJson);
        return User.fromJson(userData);
      } catch (e) {
        print('Error parsing user data: $e');
        return null;
      }
    }
    return null;
  }

  // Logout
  Future<void> logout() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getString(_userIdKey);
      final token = prefs.getString(_tokenKey);

      if (userId != null && token != null) {
        final baseUrl = await getBaseUrl();
        await http.post(
          Uri.parse(baseUrl),
          body: {
            'action': 'logout',
            'user_id': userId,
            'token': token,
          },
        ).timeout(const Duration(seconds: 5));
      }
    } catch (e) {
      print('Logout error: $e');
    } finally {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_tokenKey);
      await prefs.remove(_userKey);
      await prefs.remove(_userIdKey);
      // Don't remove server URL on logout
    }
  }

  // ==================== DASHBOARD & DATA ====================

  // Get dashboard data
  Future<Map<String, dynamic>> getDashboardData() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'dashboard',
          'user_id': userId,
          'token': token,
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

  // Get all payments
  Future<Map<String, dynamic>> getPayments() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_payments',
          'user_id': userId,
          'token': token,
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

  // Get schedule (subjects only)
  Future<Map<String, dynamic>> getSchedule() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_schedule',
          'user_id': userId,
          'token': token,
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

  // Get full schedule with times and days
  Future<Map<String, dynamic>> getFullSchedule() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_full_schedule',
          'user_id': userId,
          'token': token,
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

  // Get profile
  Future<Map<String, dynamic>> getProfile() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_profile',
          'user_id': userId,
          'token': token,
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

  // Get academic progress
  Future<Map<String, dynamic>> getAcademicProgress() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_academic_progress',
          'user_id': userId,
          'token': token,
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

  // Get recent activities
  Future<Map<String, dynamic>> getRecentActivities() async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'get_recent_activities',
          'user_id': userId,
          'token': token,
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

  // Update profile
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> profileData) async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'update_profile',
          'user_id': userId,
          'token': token,
          ...profileData,
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

  // Change password
  Future<Map<String, dynamic>> changePassword(String currentPassword, String newPassword) async {
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

      final baseUrl = await getBaseUrl();

      final response = await http.post(
        Uri.parse(baseUrl),
        body: {
          'action': 'change_password',
          'user_id': userId,
          'token': token,
          'current_password': currentPassword,
          'new_password': newPassword,
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
}