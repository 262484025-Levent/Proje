// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../services/database_service.dart';
import '../services/auth_controller.dart';
import '../models/recipe_model.dart';
import '../models/user_model.dart';
import 'recipe_detail_screen.dart';
import 'login_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final DatabaseService _dbService = DatabaseService();
  final AuthController _authController = AuthController();

  List<RecipeModel> _myRecipes = [];
  bool _isLoadingRecipes = true;

  @override
  void initState() {
    super.initState();
    _loadMyRecipes();
  }

  // Kullanıcının Kendi Tariflerini Yükle
  Future<void> _loadMyRecipes() async {
    setState(() {
      _isLoadingRecipes = true;
    });

    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0) return;

    try {
      final recipes = await _dbService.getUserRecipes(userId, loggedInUserId: userId);
      if (mounted) {
        setState(() {
          _myRecipes = recipes;
          _isLoadingRecipes = false;
        });
      }
    } catch (e) {
      print('Profil tariflerini yükleme hatası: $e');
      if (mounted) {
        setState(() {
          _isLoadingRecipes = false;
        });
      }
    }
  }

  // Profil Düzenleme Diyaloğu
  void _showEditProfileDialog() {
    final user = _authController.currentUser;
    if (user == null) return;

    final usernameController = TextEditingController(text: user.kullaniciAdi);
    final bioController = TextEditingController(text: user.biyografi ?? '');
    
    XFile? dialogSelectedImage;
    final formKey = GlobalKey<FormState>();
    final ImagePicker picker = ImagePicker();

    showDialog(
      context: context,
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            
            Future<void> pickDialogImage(ImageSource source) async {
              try {
                final XFile? image = await picker.pickImage(
                  source: source,
                  maxWidth: 512,
                  maxHeight: 512,
                  imageQuality: 85,
                );
                if (image != null) {
                  setDialogState(() {
                    dialogSelectedImage = image;
                  });
                }
              } catch (e) {
                print('Avatar seçme hatası: $e');
              }
            }

            void showAvatarSourceOptions() {
              showModalBottomSheet(
                context: context,
                shape: const RoundedRectangleBorder(
                  borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
                ),
                builder: (modalContext) => SafeArea(
                  child: Wrap(
                    children: [
                      ListTile(
                        leading: const Icon(Icons.photo_library, color: Color(0xFFFF7E36)),
                        title: const Text('Galeriden Seç'),
                        onTap: () {
                          Navigator.pop(modalContext);
                          pickDialogImage(ImageSource.gallery);
                        },
                      ),
                      ListTile(
                        leading: const Icon(Icons.photo_camera, color: Color(0xFFFF7E36)),
                        title: const Text('Kamerayla Çek'),
                        onTap: () {
                          Navigator.pop(modalContext);
                          pickDialogImage(ImageSource.camera);
                        },
                      ),
                    ],
                  ),
                ),
              );
            }

            return AlertDialog(
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
              title: Row(
                children: const [
                  Icon(Icons.edit, color: Color(0xFFFF7E36)),
                  SizedBox(width: 8),
                  Text('Profili Düzenle', style: TextStyle(fontWeight: FontWeight.bold)),
                ],
              ),
              content: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      // Avatar Seçici Önizleme
                      GestureDetector(
                        onTap: showAvatarSourceOptions,
                        child: Stack(
                          alignment: Alignment.bottomRight,
                          children: [
                            CircleAvatar(
                              radius: 40,
                              backgroundColor: const Color(0xFFFFEFE5),
                              backgroundImage: dialogSelectedImage != null
                                  ? FileImage(File(dialogSelectedImage!.path)) as ImageProvider
                                  : (user.profilResmi != null && user.profilResmi != 'default.png')
                                      ? NetworkImage(user.profilResmi!)
                                      : null,
                              child: (dialogSelectedImage == null && (user.profilResmi == null || user.profilResmi == 'default.png'))
                                  ? Text(
                                      user.kullaniciAdi.isNotEmpty ? user.kullaniciAdi[0].toUpperCase() : 'Y',
                                      style: const TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 32),
                                    )
                                  : null,
                            ),
                            const CircleAvatar(
                              backgroundColor: Color(0xFFFF7E36),
                              radius: 14,
                              child: Icon(Icons.camera_alt, size: 14, color: Colors.white),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      TextFormField(
                        controller: usernameController,
                        decoration: const InputDecoration(
                          labelText: 'Kullanıcı Adı',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'Kullanıcı adı boş bırakılamaz.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: bioController,
                        maxLines: 3,
                        decoration: const InputDecoration(
                          labelText: 'Hakkımda (Bio)',
                          border: OutlineInputBorder(),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('İptal', style: TextStyle(color: Colors.grey)),
                ),
                ElevatedButton(
                  onPressed: () async {
                    if (!formKey.currentState!.validate()) return;
                    
                    try {
                      final success = await _authController.updateProfile(
                        kullaniciAdi: usernameController.text.trim(),
                        biyografi: bioController.text.trim(),
                        localAvatarPath: dialogSelectedImage?.path,
                      );

                      if (success && mounted) {
                        Navigator.pop(context);
                        setState(() {}); // Arayüzü yenile
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Profiliniz güncellendi!'), backgroundColor: Colors.green),
                        );
                        _loadMyRecipes(); // Yazar ismi akışlarda güncellensin diye yenileyelim
                      }
                    } catch (e) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Hata: $e'), backgroundColor: Colors.red),
                      );
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFFF7E36),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: const Text('Güncelle'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  // Çıkış Yapma
  void _handleLogout() {
    _authController.logout();
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (context) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _authController.currentUser;

    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text(
          'Profilim',
          style: TextStyle(color: Color(0xFF1E2022), fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.redAccent),
            tooltip: 'Çıkış Yap',
            onPressed: _handleLogout,
          ),
        ],
      ),
      body: user == null
          ? const Center(child: Text('Lütfen giriş yapın.'))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // 1. Profil Bilgileri Kartı (Header)
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          // Büyük Avatar
                          CircleAvatar(
                            radius: 36,
                            backgroundColor: const Color(0xFFFFEFE5),
                            backgroundImage: (user.profilResmi != null && user.profilResmi != 'default.png')
                                ? NetworkImage(user.profilResmi!)
                                : null,
                            child: (user.profilResmi == null || user.profilResmi == 'default.png')
                                ? Text(
                                    user.kullaniciAdi.isNotEmpty
                                        ? user.kullaniciAdi[0].toUpperCase()
                                        : 'Y',
                                    style: const TextStyle(
                                      color: Color(0xFFFF7E36),
                                      fontWeight: FontWeight.bold,
                                      fontSize: 28,
                                    ),
                                  )
                                : null,
                          ),
                          const SizedBox(width: 18),
                          
                          // İsim ve Mail
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '@${user.kullaniciAdi}',
                                  style: const TextStyle(
                                    fontSize: 20,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF1E2022),
                                    letterSpacing: -0.5,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  user.eposta,
                                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                                ),
                                const SizedBox(height: 8),
                                
                                // Profil Düzenle Butonu
                                OutlinedButton.icon(
                                  onPressed: _showEditProfileDialog,
                                  icon: const Icon(Icons.edit, size: 14, color: Color(0xFFFF7E36)),
                                  label: const Text(
                                    'Profili Düzenle',
                                    style: TextStyle(fontSize: 12, color: Color(0xFFFF7E36), fontWeight: FontWeight.bold),
                                  ),
                                  style: OutlinedButton.styleFrom(
                                    side: const BorderSide(color: Color(0xFFFF7E36)),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                      
                      // Biyografi Alanı
                      if (user.biyografi != null && user.biyografi!.trim().isNotEmpty) ...[
                        const SizedBox(height: 16),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFFAFAFA),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: Colors.grey.shade100),
                          ),
                          child: Text(
                            user.biyografi!,
                            style: TextStyle(fontSize: 13, color: Colors.grey.shade800, height: 1.4),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                
                const SizedBox(height: 8),

                // 2. Kendi Tariflerim Başlığı
                Container(
                  color: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Paylaştığım Tarifler',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF1E2022)),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFEFE5),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          '${_myRecipes.length} Tarif',
                          style: const TextStyle(color: Color(0xFFFF7E36), fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ),

                // 3. Tariflerim Listesi
                Expanded(
                  child: RefreshIndicator(
                    onRefresh: _loadMyRecipes,
                    color: const Color(0xFFFF7E36),
                    child: _isLoadingRecipes
                        ? const Center(child: CircularProgressIndicator(color: Color(0xFFFF7E36)))
                        : _myRecipes.isEmpty
                            ? ListView(
                                children: [
                                  SizedBox(height: MediaQuery.of(context).size.height * 0.1),
                                  const Center(child: Text('🍳', style: TextStyle(fontSize: 48))),
                                  const SizedBox(height: 12),
                                  const Center(
                                    child: Text(
                                      'Henüz tarif paylaşmadınız',
                                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Colors.grey),
                                    ),
                                  ),
                                ],
                              )
                            : ListView.builder(
                                padding: const EdgeInsets.all(16),
                                itemCount: _myRecipes.length,
                                itemBuilder: (context, index) {
                                  final recipe = _myRecipes[index];
                                  return _buildRecipeListItem(recipe);
                                },
                              ),
                  ),
                ),
              ],
            ),
    );
  }

  // Küçük Tarif Hücresi
  Widget _buildRecipeListItem(RecipeModel recipe) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => RecipeDetailScreen(recipeId: recipe.id),
          ),
        ).then((_) => _loadMyRecipes());
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.02),
              blurRadius: 8,
              offset: const Offset(0, 3),
            )
          ],
        ),
        child: Row(
          children: [
            // Resim
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: SizedBox(
                width: 64,
                height: 64,
                child: (recipe.resimUrl != null && recipe.resimUrl!.startsWith('http'))
                    ? Image.network(
                        recipe.resimUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (c, o, s) => _buildPlaceholder(),
                      )
                    : _buildPlaceholder(),
              ),
            ),
            const SizedBox(width: 14),

            // Metinler
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    recipe.baslik,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF1E2022),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(Icons.star, color: Color(0xFFFFB300), size: 14),
                      const SizedBox(width: 2),
                      Text(
                        recipe.ortalamaPuan.toStringAsFixed(1),
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(width: 10),
                      Icon(Icons.favorite, color: Colors.red.shade400, size: 14),
                      const SizedBox(width: 2),
                      Text(
                        '${recipe.begeniSayisi}',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            
            const Icon(Icons.arrow_forward_ios, size: 14, color: Colors.grey),
          ],
        ),
      ),
    );
  }

  Widget _buildPlaceholder() {
    return Container(
      color: const Color(0xFFFFEFE5),
      child: const Center(
        child: Text('🍽️', style: TextStyle(fontSize: 24)),
      ),
    );
  }
}
