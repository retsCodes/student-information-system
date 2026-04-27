import 'package:flutter/material.dart';
import '../services/auth_service.dart';
import '../models.dart';

class AcademicProgressScreen extends StatefulWidget {
  const AcademicProgressScreen({Key? key}) : super(key: key);

  @override
  State<AcademicProgressScreen> createState() => _AcademicProgressScreenState();
}

class _AcademicProgressScreenState extends State<AcademicProgressScreen> {
  final AuthService _auth = AuthService();
  AcademicProgress? _progress;
  bool _isLoading = true;
  String _error = '';
  int _expandedYear = -1;

  @override
  void initState() {
    super.initState();
    _loadProgress();
  }

  Future<void> _loadProgress() async {
    setState(() {
      _isLoading = true;
      _error = '';
    });

    try {
      final result = await _auth.getAcademicProgress();

      if (result['success'] == true && result['data'] != null) {
        setState(() {
          _progress = AcademicProgress.fromJson(result['data']);
          _isLoading = false;
        });
      } else {
        _error = result['message'] ?? 'Failed to load academic progress';
      }
    } catch (e) {
      _error = 'Error: $e';
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Academic Progress'),
        backgroundColor: Colors.white,
        foregroundColor: Colors.blue,
        elevation: 1,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadProgress,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadProgress,
        child: _buildBody(),
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
            Text('Loading academic progress...'),
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
              ElevatedButton(onPressed: _loadProgress, child: const Text('Try Again')),
            ],
          ),
        ),
      );
    }

    if (_progress == null) {
      return const Center(child: Text('No academic data available'));
    }

    final percentage = _progress!.totalSubjects > 0
        ? (_progress!.completedCount / _progress!.totalSubjects) * 100
        : 0.0;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.5,
            children: [
              _buildSummaryCard('Total Subjects', '${_progress!.totalSubjects}', Icons.book, Colors.blue),
              _buildSummaryCard('Completed', '${_progress!.completedCount}', Icons.check_circle, Colors.green),
              _buildSummaryCard('In Progress', '${_progress!.inProgressCount}', Icons.hourglass_empty, Colors.orange),
              _buildSummaryCard('Average Grade', _progress!.averageGrade?.toStringAsFixed(2) ?? '—', Icons.grade, Colors.purple),
            ],
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              boxShadow: [BoxShadow(color: Colors.grey.withOpacity(0.1), blurRadius: 5)],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Overall Progress', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    Text('${percentage.toStringAsFixed(1)}%', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.blue)),
                  ],
                ),
                const SizedBox(height: 8),
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: LinearProgressIndicator(
                    value: percentage / 100,
                    minHeight: 10,
                    backgroundColor: Colors.grey.shade200,
                    valueColor: const AlwaysStoppedAnimation<Color>(Colors.blue),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),
          const Text('Curriculum Progress', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          ..._buildCurriculum(),
        ],
      ),
    );
  }

  Widget _buildSummaryCard(String title, String value, IconData icon, Color color) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.grey.withOpacity(0.1), blurRadius: 5)],
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          Text(title, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
        ],
      ),
    );
  }

  List<Widget> _buildCurriculum() {
    final widgets = <Widget>[];

    for (var year in _progress!.curriculum) {
      final isExpanded = _expandedYear == year.year;

      widgets.add(
        Card(
          margin: const EdgeInsets.only(bottom: 12),
          child: Column(
            children: [
              ListTile(
                onTap: () => setState(() => _expandedYear = isExpanded ? -1 : year.year),
                tileColor: _getYearColor(year.status),
                title: Text('YEAR ${year.year}', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                trailing: Icon(isExpanded ? Icons.expand_less : Icons.expand_more, color: Colors.white),
                subtitle: Text(_getYearSubtitle(year), style: const TextStyle(color: Colors.white70)),
              ),
              if (isExpanded)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: year.semesters.map((semester) => _buildSemesterSection(semester)).toList(),
                  ),
                ),
            ],
          ),
        ),
      );
    }

    return widgets;
  }

  Color _getYearColor(String status) {
    switch (status) {
      case 'completed': return Colors.green;
      case 'current': return Colors.orange;
      default: return Colors.grey;
    }
  }

  String _getYearSubtitle(CurriculumYear year) {
    final totalSubjects = year.semesters.fold(0, (sum, s) => sum + s.subjects.length);
    return '$totalSubjects subjects • ${year.status.toUpperCase()}';
  }

  Widget _buildSemesterSection(SemesterData semester) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          margin: const EdgeInsets.only(top: 12, bottom: 8),
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: Colors.blue.shade50, borderRadius: BorderRadius.circular(8)),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('${semester.semester} Semester', style: const TextStyle(fontWeight: FontWeight.bold)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(color: Colors.blue, borderRadius: BorderRadius.circular(12)),
                child: Text('${semester.totalUnits} units', style: const TextStyle(color: Colors.white, fontSize: 11)),
              ),
            ],
          ),
        ),
        ...semester.subjects.map((subject) => _buildSubjectTile(subject)),
      ],
    );
  }

  Widget _buildSubjectTile(CurriculumSubject subject) {
    Color statusColor;
    IconData statusIcon;

    switch (subject.status) {
      case 'completed':
        statusColor = Colors.green;
        statusIcon = Icons.check_circle;
        break;
      case 'current':
        statusColor = Colors.blue;
        statusIcon = Icons.play_circle;
        break;
      case 'missing':
        statusColor = Colors.red;
        statusIcon = Icons.warning;
        break;
      default:
        statusColor = Colors.grey;
        statusIcon = Icons.schedule;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.grey.shade50,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Colors.grey.shade200),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(6)),
            child: Icon(statusIcon, color: statusColor, size: 16),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(subject.subjectName, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
                Text(subject.subjectCode, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
              ],
            ),
          ),
          if (subject.isExtra)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(color: Colors.purple.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
              child: const Text('EXTRA', style: TextStyle(fontSize: 9, color: Colors.purple, fontWeight: FontWeight.bold)),
            ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (subject.grade != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(color: subject.getGradeColor().withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                  child: Text(subject.grade!.toStringAsFixed(2), style: TextStyle(fontWeight: FontWeight.bold, color: subject.getGradeColor())),
                ),
              Text('${subject.units} units', style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
            ],
          ),
        ],
      ),
    );
  }
}