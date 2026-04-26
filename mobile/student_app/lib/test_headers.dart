import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

void main() => runApp(HeaderTestApp());

class HeaderTestApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Header Test',
      home: HeaderTestScreen(),
    );
  }
}

class HeaderTestScreen extends StatefulWidget {
  @override
  _HeaderTestScreenState createState() => _HeaderTestScreenState();
}

class _HeaderTestScreenState extends State<HeaderTestScreen> {
  String _result = 'Press button to test';
  bool _loading = false;

  final Map<String, String> _browserHeaders = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language': 'en-US,en;q=0.9',
    'Accept-Encoding': 'gzip, deflate, br',
    'Connection': 'keep-alive',
  };

  Future<void> testWithHeaders() async {
    setState(() {
      _loading = true;
      _result = 'Testing with browser headers...';
    });

    try {
      final url = Uri.parse('https://rets.free.nf/students_information_system/mobile_api.php?action=test');

      final response = await http.get(
        url,
        headers: _browserHeaders,
      ).timeout(Duration(seconds: 10));

      print('Status: ${response.statusCode}');
      print('Response: ${response.body.substring(0, response.body.length > 200 ? 200 : response.body.length)}');

      if (response.body.contains('aes.js') || response.body.contains('<html')) {
        setState(() {
          _result = '❌ Still getting protection page. Headers not enough.';
          _loading = false;
        });
      } else {
        final data = jsonDecode(response.body);
        setState(() {
          _result = '✅ WORKING! API returned: ${data.toString()}';
          _loading = false;
        });
      }
    } catch (e) {
      setState(() {
        _result = '❌ Error: $e';
        _loading = false;
      });
    }
  }

  Future<void> testWithoutHeaders() async {
    setState(() {
      _loading = true;
      _result = 'Testing without headers...';
    });

    try {
      final url = Uri.parse('https://rets.free.nf/students_information_system/mobile_api.php?action=test');

      final response = await http.get(url).timeout(Duration(seconds: 10));

      if (response.body.contains('aes.js')) {
        setState(() {
          _result = '❌ Protection page detected (as expected without headers)';
          _loading = false;
        });
      } else {
        setState(() {
          _result = 'Response: ${response.body.substring(0, 100)}';
          _loading = false;
        });
      }
    } catch (e) {
      setState(() {
        _result = 'Error: $e';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Header Test - InfinityFree')),
      body: Padding(
        padding: EdgeInsets.all(20),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_result, textAlign: TextAlign.center, style: TextStyle(fontSize: 14)),
            SizedBox(height: 30),
            ElevatedButton(
              onPressed: _loading ? null : testWithHeaders,
              style: ElevatedButton.styleFrom(padding: EdgeInsets.symmetric(vertical: 15)),
              child: Text(_loading ? 'Testing...' : 'Test WITH Browser Headers'),
            ),
            SizedBox(height: 10),
            ElevatedButton(
              onPressed: _loading ? null : testWithoutHeaders,
              style: ElevatedButton.styleFrom(padding: EdgeInsets.symmetric(vertical: 15), backgroundColor: Colors.grey),
              child: Text(_loading ? 'Testing...' : 'Test WITHOUT Headers'),
            ),
            SizedBox(height: 30),
            Text(
              'If headers work, update your auth_service.dart permanently.\nIf not, use the temporary URL method.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}