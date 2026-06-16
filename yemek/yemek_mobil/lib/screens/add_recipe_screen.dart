// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../services/database_service.dart';
import '../services/auth_controller.dart';

class AddRecipeScreen extends StatefulWidget {
  const AddRecipeScreen({super.key});

  @override
  State<AddRecipeScreen> createState() => _AddRecipeScreenState();
}

class _AddRecipeScreenState extends State<AddRecipeScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _prepTimeController = TextEditingController();
  final _cookTimeController = TextEditingController();
  final _servingsController = TextEditingController(text: '1');
  final _ingredientsController = TextEditingController();
  final _instructionsController = TextEditingController();

  XFile? _selectedImage;
  bool _isLoading = false;
  final DatabaseService _dbService = DatabaseService();
  final AuthController _authController = AuthController();
  final ImagePicker _picker = ImagePicker();

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _prepTimeController.dispose();
    _cookTimeController.dispose();
    _servingsController.dispose();
    _ingredientsController.dispose();
    _instructionsController.dispose();
    super.dispose();
  }

  // Fotoğraf Seçme Fonksiyonu
  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 85,
      );
      if (image != null) {
        setState(() {
          _selectedImage = image;
        });
      }
    } catch (e) {
      print('Fotoğraf seçme hatası: $e');
    }
  }

  // Fotoğraf Seçenekleri Alt Menüsü
  void _showImagePickerOptions() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFFFF7E36)),
              title: const Text('Galeriden Seç'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.gallery);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera, color: Color(0xFFFF7E36)),
              title: const Text('Kamerayla Çek'),
              onTap: () {
                Navigator.pop(context);
                _pickImage(ImageSource.camera);
              },
            ),
            if (_selectedImage != null)
              ListTile(
                leading: const Icon(Icons.delete, color: Colors.red),
                title: const Text('Seçilen Fotoğrafı Kaldır'),
                onTap: () {
                  Navigator.pop(context);
                  setState(() {
                    _selectedImage = null;
                  });
                },
              ),
          ],
        ),
      ),
    );
  }

  // Tarif Kaydetme
  Future<void> _saveRecipe() async {
    if (!_formKey.currentState!.validate()) return;

    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lütfen önce giriş yapın.'), backgroundColor: Colors.red),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final insertId = await _dbService.addRecipe(
        userId: userId,
        title: _titleController.text.trim(),
        description: _descriptionController.text.trim(),
        ingredients: _ingredientsController.text.trim(),
        instructions: _instructionsController.text.trim(),
        prepTime: int.tryParse(_prepTimeController.text.trim()),
        cookTime: int.tryParse(_cookTimeController.text.trim()),
        servings: int.tryParse(_servingsController.text.trim()) ?? 1,
        localImagePath: _selectedImage?.path,
      );

      if (insertId != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tarifiniz başarıyla eklendi!'), backgroundColor: Colors.green),
        );
        _clearForm();
      }
    } catch (e) {
      print('Tarif ekleme hatası: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Hata oluştu: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // Formu Temizleme
  void _clearForm() {
    _titleController.clear();
    _descriptionController.clear();
    _prepTimeController.clear();
    _cookTimeController.clear();
    _servingsController.text = '1';
    _ingredientsController.clear();
    _instructionsController.clear();
    _selectedImage = null;
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text(
          'Yeni Tarif Paylaş',
          style: TextStyle(color: Color(0xFF1E2022), fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
        actions: [
          TextButton.icon(
            onPressed: _isLoading ? null : _saveRecipe,
            icon: const Icon(Icons.check, color: Color(0xFFFF7E36)),
            label: const Text(
              'Kaydet',
              style: TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 16),
            ),
          )
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFFF7E36)))
          : Form(
              key: _formKey,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Tarif Başlığı
                    _buildSectionHeader('Tarif Bilgileri'),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _titleController,
                      decoration: _buildInputDecoration('Tarif Adı', 'Örn: Mercimek Çorbası'),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Lütfen tarif adı girin.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),

                    // Kısa Açıklama
                    TextFormField(
                      controller: _descriptionController,
                      maxLines: 2,
                      decoration: _buildInputDecoration('Kısa Açıklama / Hikaye', 'Tarif hakkında kısa bilgi...'),
                    ),
                    const SizedBox(height: 16),

                    // Süre ve Porsiyon Alanları (Row)
                    Row(
                      children: [
                        // Hazırlık Süresi
                        Expanded(
                          child: TextFormField(
                            controller: _prepTimeController,
                            keyboardType: TextInputType.number,
                            decoration: _buildInputDecoration('Hazırlık (dk)', '15'),
                            validator: (value) {
                              if (value != null && value.trim().isNotEmpty && int.tryParse(value) == null) {
                                  return 'Rakam olmalı';
                              }
                              return null;
                            },
                          ),
                        ),
                        const SizedBox(width: 12),
                        // Pişirme Süresi
                        Expanded(
                          child: TextFormField(
                            controller: _cookTimeController,
                            keyboardType: TextInputType.number,
                            decoration: _buildInputDecoration('Pişirme (dk)', '20'),
                            validator: (value) {
                              if (value != null && value.trim().isNotEmpty && int.tryParse(value) == null) {
                                return 'Rakam olmalı';
                              }
                              return null;
                            },
                          ),
                        ),
                        const SizedBox(width: 12),
                        // Porsiyon
                        Expanded(
                          child: TextFormField(
                            controller: _servingsController,
                            keyboardType: TextInputType.number,
                            decoration: _buildInputDecoration('Porsiyon', '4'),
                            validator: (value) {
                              if (value == null || value.trim().isEmpty || int.tryParse(value) == null) {
                                return 'Gerekli';
                              }
                              return null;
                            },
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Görsel Seçici (Fotoğraf Yükleme Sistemi)
                    _buildSectionHeader('Tarif Görseli'),
                    const SizedBox(height: 12),
                    _selectedImage == null
                        ? GestureDetector(
                            onTap: _showImagePickerOptions,
                            child: Container(
                              height: 180,
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFEFE5),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: const Color(0xFFFF7E36).withOpacity(0.5), width: 1.5),
                              ),
                              child: Center(
                                child: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const Icon(Icons.add_a_photo_outlined, size: 48, color: Color(0xFFFF7E36)),
                                    const SizedBox(height: 10),
                                    const Text(
                                      'Yemek Fotoğrafı Ekle',
                                      style: TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 16),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Galeriden seçin veya kamerayla çekin',
                                      style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          )
                        : Stack(
                            children: [
                              ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: Image.file(
                                  File(_selectedImage!.path),
                                  height: 200,
                                  width: double.infinity,
                                  fit: BoxFit.cover,
                                ),
                              ),
                              Positioned(
                                right: 8,
                                top: 8,
                                child: CircleAvatar(
                                  backgroundColor: Colors.black.withOpacity(0.6),
                                  radius: 18,
                                  child: IconButton(
                                    icon: const Icon(Icons.close, size: 18, color: Colors.white),
                                    onPressed: () {
                                      setState(() {
                                        _selectedImage = null;
                                      });
                                    },
                                  ),
                                ),
                              ),
                              Positioned(
                                left: 8,
                                bottom: 8,
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: Colors.black.withOpacity(0.6),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: const Text(
                                    'Görseli Değiştirmek İçin Dokunun',
                                    style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ),
                              Positioned.fill(
                                child: Material(
                                  color: Colors.transparent,
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(12),
                                    onTap: _showImagePickerOptions,
                                  ),
                                ),
                              ),
                            ],
                          ),
                    const SizedBox(height: 24),

                    // Malzemeler
                    _buildSectionHeader('Malzemeler'),
                    const SizedBox(height: 4),
                    const Text(
                      'Her bir malzemeyi yeni bir satıra yazın.',
                      style: TextStyle(color: Colors.grey, fontSize: 11),
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _ingredientsController,
                      maxLines: 6,
                      decoration: _buildInputDecoration(
                        'Malzeme Listesi',
                        'Örn:\n1 su bardağı mercimek\n1 adet soğan\n1 yemek kaşığı tereyağı',
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Lütfen malzemeleri girin.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),

                    // Hazırlanışı
                    _buildSectionHeader('Nasıl Hazırlanır?'),
                    const SizedBox(height: 4),
                    const Text(
                      'Her bir adımı yeni bir satıra yazın.',
                      style: TextStyle(color: Colors.grey, fontSize: 11),
                    ),
                    const SizedBox(height: 8),
                    TextFormField(
                      controller: _instructionsController,
                      maxLines: 8,
                      decoration: _buildInputDecoration(
                        'Hazırlama Adımları',
                        'Örn:\nMercimekleri güzelce yıkayın.\nSoğanları doğrayıp tencerede pembeleşene kadar kavurun.\nSuyu ekleyip mercimekler yumuşayana kadar kaynatın.',
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Lütfen hazırlama adımlarını girin.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 32),

                    // Büyük Kaydet Butonu
                    ElevatedButton(
                      onPressed: _saveRecipe,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFF7E36),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 16),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        elevation: 1,
                      ),
                      child: const Text(
                        'Tarifi Yayına Al',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
    );
  }

  // Bölüm Başlığı Oluşturucu
  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.bold,
        color: Color(0xFF1E2022),
      ),
    );
  }

  // Input Dekorasyonu
  InputDecoration _buildInputDecoration(String label, String hint) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: Colors.grey.shade300),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFFF7E36), width: 1.5),
      ),
    );
  }
}
