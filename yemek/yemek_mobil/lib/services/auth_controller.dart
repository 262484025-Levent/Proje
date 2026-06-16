// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'package:flutter/material.dart';
import '../models/user_model.dart';
import 'database_service.dart';

class AuthController extends ChangeNotifier {
  static final AuthController _instance = AuthController._internal();
  factory AuthController() => _instance;
  AuthController._internal();

  UserModel? _currentUser;
  UserModel? get currentUser => _currentUser;
  bool get isLoggedIn => _currentUser != null;

  final DatabaseService _db = DatabaseService();

  // Giriş Yap
  Future<bool> login(String email, String password) async {
    try {
      final user = await _db.login(email, password);
      if (user != null) {
        _currentUser = user;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      print('Giriş hatası: $e');
      return false;
    }
  }

  // Kayıt Ol
  Future<bool> register(String username, String email, String password) async {
    try {
      final user = await _db.register(username, email, password);
      _currentUser = user;
      notifyListeners();
      return true;
    } catch (e) {
      print('Kayıt hatası: $e');
      rethrow;
    }
  }

  // Profil Güncelle
  Future<bool> updateProfile({
    required String kullaniciAdi,
    required String biyografi,
    String? profilResmi,
    String? localAvatarPath,
  }) async {
    if (_currentUser == null) return false;
    try {
      final updatedUser = await _db.updateProfile(
        userId: _currentUser!.id,
        username: kullaniciAdi,
        bio: biyografi,
        avatar: profilResmi,
        localAvatarPath: localAvatarPath,
      );
      if (updatedUser != null) {
        _currentUser = updatedUser;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      print('Profil güncelleme hatası: $e');
      rethrow;
    }
  }

  // Çıkış Yap
  void logout() {
    _currentUser = null;
    notifyListeners();
  }
}
