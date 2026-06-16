// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'package:flutter/material.dart';
import 'feed_screen.dart';
import 'add_recipe_screen.dart';
import 'top_rated_screen.dart';
import 'profile_screen.dart';
import '../services/auth_controller.dart';

class MainNavigation extends StatefulWidget {
  const MainNavigation({super.key});

  @override
  State<MainNavigation> createState() => _MainNavigationState();
}

class _MainNavigationState extends State<MainNavigation> {
  int _currentIndex = 0;

  final List<Widget> _screens = [
    const FeedScreen(),
    const AddRecipeScreen(),
    const TopRatedScreen(),
    const ProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.06),
              blurRadius: 16,
              spreadRadius: 0,
              offset: const Offset(0, -4),
            )
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) {
            setState(() {
              _currentIndex = index;
            });
          },
          type: BottomNavigationBarType.fixed,
          backgroundColor: Colors.white,
          selectedItemColor: const Color(0xFFFF7E36),
          unselectedItemColor: const Color(0xFF8E8E93),
          selectedLabelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
          unselectedLabelStyle: const TextStyle(fontSize: 12),
          elevation: 0,
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.home_outlined),
              activeIcon: Icon(Icons.home, color: Color(0xFFFF7E36)),
              label: 'Akış',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.add_circle_outline),
              activeIcon: Icon(Icons.add_circle, color: Color(0xFFFF7E36)),
              label: 'Tarif Ekle',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.leaderboard_outlined),
              activeIcon: Icon(Icons.leaderboard, color: Color(0xFFFF7E36)),
              label: 'En İyiler',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_outline),
              activeIcon: Icon(Icons.person, color: Color(0xFFFF7E36)),
              label: 'Profil',
            ),
          ],
        ),
      ),
    );
  }
}
