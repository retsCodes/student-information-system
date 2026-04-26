import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../models.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({Key? key}) : super(key: key);

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  final AuthService _auth = AuthService();
  List<ClassSchedule> _schedule = [];
  bool _isLoading = true;
  String _error = '';
  String _selectedDay = 'Monday';

  final List<String> _days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  @override
  void initState() {
    super.initState();
    _loadSchedule();
  }

  Future<void> _loadSchedule() async {
    setState(() {
      _isLoading = true;
      _error = '';
    });

    try {
      final result = await _auth.getFullSchedule();

      if (result['success'] == true) {
        setState(() {
          _schedule = (result['data'] as List)
              .map((item) => ClassSchedule.fromJson(item))
              .toList();
        });
      } else {
        _error = result['message'] ?? 'Failed to load schedule';
      }
    } catch (e) {
      _error = 'Error: $e';
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  List<ClassSchedule> get _filteredSchedule {
    return _schedule.where((c) => c.dayOfWeek == _selectedDay).toList();
  }

  // Helper to parse time strings
  DateTime _parseTime(String timeString) {
    try {
      if (timeString.contains(' ')) {
        // Format like "8:00 AM"
        final parts = timeString.split(' ');
        final timeParts = parts[0].split(':');
        int hour = int.parse(timeParts[0]);
        final minute = int.parse(timeParts[1]);
        final isPM = parts[1].toUpperCase() == 'PM';
        if (isPM && hour != 12) hour += 12;
        if (!isPM && hour == 12) hour = 0;
        return DateTime(2025, 1, 1, hour, minute);
      } else {
        // Format like "08:00:00"
        final parts = timeString.split(':');
        final hour = int.parse(parts[0]);
        final minute = int.parse(parts[1]);
        return DateTime(2025, 1, 1, hour, minute);
      }
    } catch (e) {
      return DateTime(2025, 1, 1, 0, 0);
    }
  }

  // Determine if class is ongoing or upcoming
  String _getClassStatus(String startTime, String endTime) {
    final now = DateTime.now();
    final currentTime = TimeOfDay.now();
    final start = _parseTime(startTime);
    final end = _parseTime(endTime);
    final currentMinutes = currentTime.hour * 60 + currentTime.minute;
    final startMinutes = start.hour * 60 + start.minute;
    final endMinutes = end.hour * 60 + end.minute;

    if (currentMinutes >= startMinutes && currentMinutes <= endMinutes) {
      return 'ongoing';
    } else if (currentMinutes < startMinutes && (startMinutes - currentMinutes) <= 60) {
      return 'upcoming';
    }
    return 'normal';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Class Schedule'),
        backgroundColor: Colors.white,
        foregroundColor: Colors.blue,
        elevation: 1,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadSchedule,
          ),
        ],
      ),
      body: Column(
        children: [
          // Day selector
          Container(
            height: 50,
            margin: const EdgeInsets.symmetric(vertical: 8),
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 8),
              itemCount: _days.length,
              itemBuilder: (context, index) {
                final day = _days[index];
                final isSelected = day == _selectedDay;
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: FilterChip(
                    label: Text(day),
                    selected: isSelected,
                    onSelected: (selected) {
                      setState(() {
                        _selectedDay = day;
                      });
                    },
                    backgroundColor: Colors.grey.shade100,
                    selectedColor: Colors.blue.shade100,
                    labelStyle: TextStyle(
                      color: isSelected ? Colors.blue : Colors.black87,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                );
              },
            ),
          ),
          // Schedule content
          Expanded(
            child: RefreshIndicator(
              onRefresh: _loadSchedule,
              child: _buildBody(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 20),
            Text('Loading schedule...'),
          ],
        ),
      );
    }

    if (_error.isNotEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 60, color: Colors.red.shade300),
              const SizedBox(height: 20),
              Text(_error, textAlign: TextAlign.center),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: _loadSchedule,
                child: const Text('Try Again'),
              ),
            ],
          ),
        ),
      );
    }

    if (_schedule.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.calendar_today, size: 60, color: Colors.grey),
            SizedBox(height: 20),
            Text('No schedule available', style: TextStyle(fontSize: 18, color: Colors.grey)),
            SizedBox(height: 10),
            Text('Your class schedule will appear here', style: TextStyle(color: Colors.grey)),
          ],
        ),
      );
    }

    if (_filteredSchedule.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.free_breakfast, size: 60, color: Colors.grey.shade400),
            const SizedBox(height: 20),
            Text(
              'No classes on $_selectedDay',
              style: TextStyle(fontSize: 18, color: Colors.grey.shade600, fontWeight: FontWeight.w500),
            ),
            const SizedBox(height: 10),
            Text('Enjoy your free time! 🎉', style: TextStyle(color: Colors.grey.shade500)),
          ],
        ),
      );
    }

    // Sort classes by start time
    final sortedClasses = List.of(_filteredSchedule);
    sortedClasses.sort((a, b) {
      final timeA = _parseTime(a.startTime);
      final timeB = _parseTime(b.startTime);
      return timeA.compareTo(timeB);
    });

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: sortedClasses.length,
      itemBuilder: (context, index) {
        final classItem = sortedClasses[index];
        final status = _getClassStatus(classItem.startTime, classItem.endTime);
        return _buildClassCard(classItem, status);
      },
    );
  }

  Widget _buildClassCard(ClassSchedule classItem, String status) {
    Color statusColor;
    String statusText;
    switch (status) {
      case 'ongoing':
        statusColor = Colors.green;
        statusText = 'ONGOING';
        break;
      case 'upcoming':
        statusColor = Colors.orange;
        statusText = 'UPCOMING';
        break;
      default:
        statusColor = Colors.grey;
        statusText = '';
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: status == 'ongoing' ? Border.all(color: Colors.green, width: 2) : null,
        ),
        child: Column(
          children: [
            // Time and status bar
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: status == 'ongoing'
                    ? Colors.green.shade50
                    : status == 'upcoming'
                        ? Colors.orange.shade50
                        : Colors.grey.shade50,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(Icons.access_time, size: 16, color: statusColor),
                      const SizedBox(width: 8),
                      Text(
                        classItem.getTimeRange(),
                        style: TextStyle(fontWeight: FontWeight.bold, color: statusColor),
                      ),
                    ],
                  ),
                  if (statusText.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: statusColor, borderRadius: BorderRadius.circular(12)),
                      child: Text(statusText, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                ],
              ),
            ),
            // Class details
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(classItem.subjectName, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: Colors.blue.shade50, borderRadius: BorderRadius.circular(8)),
                        child: Text(classItem.subjectCode, style: TextStyle(color: Colors.blue.shade700, fontWeight: FontWeight.w500, fontSize: 12)),
                      ),
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(color: Colors.purple.shade50, borderRadius: BorderRadius.circular(8)),
                        child: Text(classItem.sectionCode, style: TextStyle(color: Colors.purple.shade700, fontWeight: FontWeight.w500, fontSize: 12)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Icon(Icons.location_on, size: 14, color: Colors.grey.shade600),
                      const SizedBox(width: 4),
                      Text(classItem.room ?? 'Room TBA', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}