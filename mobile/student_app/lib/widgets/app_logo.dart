import 'package:flutter/material.dart';

class AppLogo extends StatelessWidget {
  final double height;
  final double width;
  final bool showText;

  const AppLogo({
    Key? key,
    this.height = 40,
    this.width = 40,
    this.showText = false,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          'assets/images/logo.jpg',
          height: height,
          width: width,
          fit: BoxFit.contain,
          errorBuilder: (context, error, stackTrace) {
            // Fallback if image not found
            return Container(
              height: height,
              width: width,
              decoration: BoxDecoration(
                color: Colors.blue.shade100,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(
                Icons.school,
                color: Colors.blue,
                size: 24,
              ),
            );
          },
        ),
        if (showText) ...[
          const SizedBox(width: 8),
          const Text(
            'SIS',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: Colors.blue,
            ),
          ),
        ],
      ],
    );
  }
}