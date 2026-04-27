import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../widgets/app_logo.dart';  // Add this import
import 'dashboard_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _userIdController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final AuthService _auth = AuthService();

  bool _isLoading = false;
  bool _obscurePassword = true;
  String _errorMessage = '';
  bool _connectionTested = false;
  bool _connectionTesting = false;

  // Demo accounts
  final Map<String, String> _demoStudent = {
    'role': 'Student',
    'id': 'C26-02-9927-MAN121',
    'pass': 'rets123',
  };

  @override
  void initState() {
    super.initState();
    // Auto-test connection when screen loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _testConnection();
    });
  }

  @override
  void dispose() {
    _userIdController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  // Test server connection
  Future<void> _testConnection() async {
    setState(() {
      _connectionTesting = true;
      _errorMessage = '';
    });

    print('Testing connection to API...');

    try {
      final result = await _auth.testConnection();
      print('Connection test result: $result');

      setState(() {
        _connectionTesting = false;
        _connectionTested = true;
        if (result['success'] == true) {
          _errorMessage = '✅ Server connection successful!';
        } else {
          _errorMessage = '❌ Connection failed: ${result['message']}';

          // Add troubleshooting tips
          if (result['message'].toString().contains('HTML')) {
            _errorMessage += '\n\nTroubleshooting:\n'
                '1. Check if PHP server is running\n'
                '2. Verify the API file path is correct\n'
                '3. Check PHP error logs';
          }
        }
      });
    } catch (e) {
      print('Connection test error: $e');
      setState(() {
        _connectionTesting = false;
        _connectionTested = true;
        _errorMessage = '❌ Connection error: $e\n\n'
            'Make sure:\n'
            '• Your PHP server is running\n'
            '• API file exists at correct location\n'
            '• For emulator: Use http://10.0.2.2/\n'
            '• For device: Use your computer\'s IP';
      });
    }
  }

  // Handle login
  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    print('Attempting login with ID: ${_userIdController.text}');

    try {
      final result = await _auth.login(
        _userIdController.text.trim(),
        _passwordController.text,
      );

      print('Login result: $result');

      if (result['success'] == true) {
        final userData = result['user'];

        // Check if user is student
        if (userData['role'] == 'student') {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(builder: (context) => const DashboardScreen()),
          );
        } else {
          setState(() {
            _errorMessage = 'Only student login is available in mobile app';
            _isLoading = false;
          });
        }
      } else {
        setState(() {
          _errorMessage = result['message'] ?? 'Login failed';
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Login error: $e');
      setState(() {
        _errorMessage = 'Login error: $e';
        _isLoading = false;
      });
    }
  }

  // Fill demo account
  void _fillDemoAccount() {
    setState(() {
      _userIdController.text = _demoStudent['id']!;
      _passwordController.text = _demoStudent['pass']!;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.blue, Colors.deepPurple],
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const SizedBox(height: 40),

                  // LOGO - REPLACED WITH APP LOGO WIDGET
                  // This now uses the AppLogo widget which loads logo.jpg
                  Container(
                    width: 120,
                    height: 120,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(60),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.2),
                          blurRadius: 10,
                          spreadRadius: 2,
                        ),
                      ],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(60),
                      child: const AppLogo(
                        height: 100,
                        width: 100,
                      ),
                    ),
                  ),

                  const SizedBox(height: 30),

                  // Title
                  const Text(
                    'Student Information System',
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                    ),
                    textAlign: TextAlign.center,
                  ),

                  const SizedBox(height: 10),

                  const Text(
                    'Mobile Student Login',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.white70,
                    ),
                  ),

                  const SizedBox(height: 40),

                  // Login Card
                  Card(
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(20),
                    ),
                    elevation: 10,
                    child: Padding(
                      padding: const EdgeInsets.all(25),
                      child: Column(
                        children: [
                          // Connection test button
                          Container(
                            padding: const EdgeInsets.all(12),
                            margin: const EdgeInsets.only(bottom: 20),
                            decoration: BoxDecoration(
                              color: _connectionTested && _errorMessage.contains('✅')
                                  ? Colors.green.shade50
                                  : Colors.orange.shade50,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: _connectionTested && _errorMessage.contains('✅')
                                    ? Colors.green.shade200
                                    : Colors.orange.shade200,
                              ),
                            ),
                            child: Column(
                              children: [
                                Text(
                                  _connectionTested && _errorMessage.contains('✅')
                                      ? '✅ Connection Ready'
                                      : 'Server Connection',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: _connectionTested && _errorMessage.contains('✅')
                                        ? Colors.green
                                        : Colors.orange,
                                  ),
                                ),
                                const SizedBox(height: 10),
                                SizedBox(
                                  width: double.infinity,
                                  child: ElevatedButton(
                                    onPressed: _testConnection,
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: _connectionTested && _errorMessage.contains('✅')
                                          ? Colors.green
                                          : Colors.orange,
                                    ),
                                    child: _connectionTesting
                                        ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                        color: Colors.white,
                                        strokeWidth: 2,
                                      ),
                                    )
                                        : const Text('Test Connection'),
                                  ),
                                ),
                              ],
                            ),
                          ),

                          // Error/Success messages
                          if (_errorMessage.isNotEmpty)
                            Container(
                              padding: const EdgeInsets.all(12),
                              margin: const EdgeInsets.only(bottom: 20),
                              decoration: BoxDecoration(
                                color: _errorMessage.contains('✅')
                                    ? Colors.green.shade50
                                    : Colors.red.shade50,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                  color: _errorMessage.contains('✅')
                                      ? Colors.green.shade200
                                      : Colors.red.shade200,
                                ),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Icon(
                                        _errorMessage.contains('✅')
                                            ? Icons.check_circle
                                            : Icons.error,
                                        color: _errorMessage.contains('✅')
                                            ? Colors.green
                                            : Colors.red,
                                      ),
                                      const SizedBox(width: 10),
                                      Text(
                                        _errorMessage.contains('✅')
                                            ? 'Success'
                                            : 'Error',
                                        style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: _errorMessage.contains('✅')
                                              ? Colors.green
                                              : Colors.red,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    _errorMessage,
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: _errorMessage.contains('✅')
                                          ? Colors.green.shade800
                                          : Colors.red.shade800,
                                    ),
                                  ),
                                ],
                              ),
                            ),

                          // User ID field
                          TextFormField(
                            controller: _userIdController,
                            decoration: InputDecoration(
                              labelText: 'Student ID',
                              hintText: 'e.g., C26-02-9927-MAN121',
                              prefixIcon: const Icon(Icons.person),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                              filled: true,
                              fillColor: Colors.grey.shade50,
                            ),
                            validator: (value) {
                              if (value == null || value.isEmpty) {
                                return 'Please enter Student ID';
                              }
                              return null;
                            },
                          ),

                          const SizedBox(height: 20),

                          // Password field
                          TextFormField(
                            controller: _passwordController,
                            obscureText: _obscurePassword,
                            decoration: InputDecoration(
                              labelText: 'Password',
                              prefixIcon: const Icon(Icons.lock),
                              suffixIcon: IconButton(
                                icon: Icon(
                                  _obscurePassword
                                      ? Icons.visibility
                                      : Icons.visibility_off,
                                ),
                                onPressed: () {
                                  setState(() {
                                    _obscurePassword = !_obscurePassword;
                                  });
                                },
                              ),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                              filled: true,
                              fillColor: Colors.grey.shade50,
                            ),
                            validator: (value) {
                              if (value == null || value.isEmpty) {
                                return 'Please enter password';
                              }
                              return null;
                            },
                          ),

                          const SizedBox(height: 25),

                          // Login button
                          SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: ElevatedButton(
                              onPressed: (_isLoading || !_connectionTested || !_errorMessage.contains('✅'))
                                  ? null
                                  : _handleLogin,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.blue,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                              ),
                              child: _isLoading
                                  ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                                  : const Text(
                                'SIGN IN',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 20),

                          // Demo button
                          SizedBox(
                            width: double.infinity,
                            height: 40,
                            child: OutlinedButton.icon(
                              onPressed: _fillDemoAccount,
                              style: OutlinedButton.styleFrom(
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                side: const BorderSide(color: Colors.green),
                              ),
                              icon: const Icon(Icons.visibility, size: 16),
                              label: const Text(
                                'Use Demo Account',
                                style: TextStyle(color: Colors.green),
                              ),
                            ),
                          ),

                          const SizedBox(height: 15),

                          // Demo info
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.green.shade50,
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: Colors.green.shade200),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Demo Student Account:',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Colors.green,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Text('ID: ${_demoStudent['id']}'),
                                Text('Password: ${_demoStudent['pass']}'),
                              ],
                            ),
                          ),

                          const SizedBox(height: 20),

                          // Admin notice
                          const Text(
                            'Note: Admin, Cashier, and Registrar login is only available on the web version.',
                            style: TextStyle(
                              color: Colors.grey,
                              fontSize: 12,
                            ),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 40),

                  // Server info for debugging
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Column(
                      children: [
                        const Text(
                          'Debug Info:',
                          style: TextStyle(
                            color: Colors.white70,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 5),
                        FutureBuilder(
                          future: _auth.testConnection(),
                          builder: (context, snapshot) {
                            if (snapshot.connectionState == ConnectionState.waiting) {
                              return const Text(
                                'Checking server...',
                                style: TextStyle(color: Colors.white70, fontSize: 12),
                              );
                            }
                            if (snapshot.hasError) {
                              return Text(
                                'Error: ${snapshot.error}',
                                style: const TextStyle(color: Colors.red, fontSize: 12),
                              );
                            }
                            return Text(
                              'Server status: ${snapshot.data?['success'] == true ? 'Online' : 'Offline'}',
                              style: TextStyle(
                                color: snapshot.data?['success'] == true ? Colors.green : Colors.red,
                                fontSize: 12,
                              ),
                            );
                          },
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Footer
                  const Text(
                    'Student Information System v1.0',
                    style: TextStyle(
                      color: Colors.white70,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}