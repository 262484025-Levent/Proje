// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'package:flutter/material.dart';
import '../services/database_service.dart';
import '../services/auth_controller.dart';
import '../models/recipe_model.dart';
import 'recipe_detail_screen.dart';

class FeedScreen extends StatefulWidget {
  const FeedScreen({super.key});

  @override
  State<FeedScreen> createState() => _FeedScreenState();
}

class _FeedScreenState extends State<FeedScreen> {
  final DatabaseService _dbService = DatabaseService();
  final AuthController _authController = AuthController();
  final TextEditingController _searchController = TextEditingController();

  List<RecipeModel> _recipes = [];
  bool _isLoading = true;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _loadRecipes();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // Tariflerin Veritabanından Yüklenmesi
  Future<void> _loadRecipes() async {
    if (!mounted) return;
    setState(() {
      _isLoading = true;
    });

    try {
      final userId = _authController.currentUser?.id ?? 0;
      final recipes = await _dbService.getRecipes(
        search: _searchQuery,
        loggedInUserId: userId,
      );
      if (mounted) {
        setState(() {
          _recipes = recipes;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Akış yükleme hatası: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // Arama Temizle/Yap
  void _onSearchChanged(String value) {
    setState(() {
      _searchQuery = value;
    });
    _loadRecipes();
  }

  // Beğeni Ekle/Kaldır Entegrasyonu (Kalp Animasyonuyla)
  Future<void> _toggleLike(RecipeModel recipe) async {
    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0) return;

    // Arayüzde anlık (optimistic) güncelleme yapalım
    final isLikedNow = !recipe.begendiMi;
    final updatedLikeCount = isLikedNow ? recipe.begeniSayisi + 1 : recipe.begeniSayisi - 1;

    setState(() {
      final index = _recipes.indexWhere((r) => r.id == recipe.id);
      if (index != -1) {
        _recipes[index] = recipe.copyWith(
          begendiMi: isLikedNow,
          begeniSayisi: updatedLikeCount,
        );
      }
    });

    try {
      // Veritabanı sorgusu gönder
      await _dbService.toggleLike(userId, recipe.id);
    } catch (e) {
      print('Beğeni gönderilemedi: $e');
      // Hata olursa eski haline geri al
      setState(() {
        final index = _recipes.indexWhere((r) => r.id == recipe.id);
        if (index != -1) {
          _recipes[index] = recipe;
        }
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: Row(
          children: [
            const Text(
              'Yemek',
              style: TextStyle(color: Color(0xFF1E2022), fontWeight: FontWeight.bold, fontSize: 22),
            ),
            const Text(
              'Paylaşım',
              style: TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 22),
            ),
          ],
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Color(0xFFFF7E36)),
            onPressed: _loadRecipes,
          ),
        ],
      ),
      body: Column(
        children: [
          // Arama Çubuğu
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 12.0),
            child: TextField(
              controller: _searchController,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Tarif, malzeme veya açıklama ara...',
                prefixIcon: const Icon(Icons.search, color: Colors.grey),
                suffixIcon: _searchQuery.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, color: Colors.grey),
                        onPressed: () {
                          _searchController.clear();
                          _onSearchChanged('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: const Color(0xFFF3F3F3),
                contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
          ),

          // Tarifler Listesi
          Expanded(
            child: RefreshIndicator(
              onRefresh: _loadRecipes,
              color: const Color(0xFFFF7E36),
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(color: Color(0xFFFF7E36)),
                    )
                  : _recipes.isEmpty
                      ? ListView(
                          children: [
                            SizedBox(height: MediaQuery.of(context).size.height * 0.2),
                            const Center(
                              child: Text(
                                '🍽️',
                                style: TextStyle(fontSize: 64),
                              ),
                            ),
                            const SizedBox(height: 16),
                            const Center(
                              child: Text(
                                'Tarif Bulunamadı',
                                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E2022)),
                              ),
                            ),
                            const SizedBox(height: 8),
                            const Center(
                              child: Text(
                                'İlk lezzeti eklemek için Tarif Ekle sekmesine geçin!',
                                style: TextStyle(color: Colors.grey, fontSize: 13),
                              ),
                            ),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _recipes.length,
                          itemBuilder: (context, index) {
                            final recipe = _recipes[index];
                            return _buildRecipeCard(recipe);
                          },
                        ),
            ),
          ),
        ],
      ),
    );
  }

  // Şık Tarif Kartı Bileşeni
  Widget _buildRecipeCard(RecipeModel recipe) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => RecipeDetailScreen(recipeId: recipe.id),
          ),
        ).then((_) => _loadRecipes()); // Detaylardan geri dönüldüğünde akışı yenile
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            )
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Yazar Başlığı (Avatar ve Tarih)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: const Color(0xFFFFEFE5),
                    backgroundImage: (recipe.yazarResmi != null && recipe.yazarResmi != 'default.png')
                        ? NetworkImage(recipe.yazarResmi!)
                        : null,
                    child: (recipe.yazarResmi == null || recipe.yazarResmi == 'default.png')
                        ? Text(
                            recipe.yazarAdi != null && recipe.yazarAdi!.isNotEmpty
                                ? recipe.yazarAdi![0].toUpperCase()
                                : 'Y',
                            style: const TextStyle(
                              color: Color(0xFFFF7E36),
                              fontWeight: FontWeight.bold,
                              fontSize: 14,
                            ),
                          )
                        : null,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '@${recipe.yazarAdi ?? "anonim"}',
                          style: const TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                            color: Color(0xFF1E2022),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          '${recipe.olusturulmaTarihi.day}.${recipe.olusturulmaTarihi.month}.${recipe.olusturulmaTarihi.year} ${recipe.olusturulmaTarihi.hour.toString().padLeft(2, '0')}:${recipe.olusturulmaTarihi.minute.toString().padLeft(2, '0')}',
                          style: const TextStyle(
                            fontSize: 10,
                            color: Colors.grey,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFEFE5),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      'Tarif',
                      style: TextStyle(
                        color: Color(0xFFFF7E36),
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // Tarif Görseli
            ClipRRect(
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: (recipe.resimUrl != null && recipe.resimUrl!.startsWith('http'))
                    ? Image.network(
                        recipe.resimUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => _buildPlaceholderImage(),
                      )
                    : _buildPlaceholderImage(),
              ),
            ),

            // Gövde (Başlık, Açıklama, Puanlar)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    recipe.baslik,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF1E2022),
                    ),
                  ),
                  const SizedBox(height: 6),
                  
                  // Yıldız Puanı Çubuğu
                  Row(
                    children: [
                      const Icon(Icons.star, color: Color(0xFFFFB300), size: 16),
                      const SizedBox(width: 4),
                      Text(
                        recipe.ortalamaPuan.toStringAsFixed(1),
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF1E2022),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '(${recipe.puanSayisi} değerlendirme)',
                        style: const TextStyle(
                          fontSize: 11,
                          color: Colors.grey,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  if (recipe.aciklama != null && recipe.aciklama!.trim().isNotEmpty) ...[
                    Text(
                      recipe.aciklama!,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 13,
                        color: Colors.grey.shade600,
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],

                  // Alt Eylem Barı (Beğen, Yorum Yap)
                  const Divider(height: 1),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      // Beğen Butonu (Animasyonlu Kalp)
                      InkWell(
                        onTap: () => _toggleLike(recipe),
                        borderRadius: BorderRadius.circular(8),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          child: Row(
                            children: [
                              AnimatedSwitcher(
                                duration: const Duration(milliseconds: 200),
                                transitionBuilder: (child, animation) => ScaleTransition(scale: animation, child: child),
                                child: Icon(
                                  recipe.begendiMi ? Icons.favorite : Icons.favorite_border,
                                  key: ValueKey<bool>(recipe.begendiMi),
                                  color: recipe.begendiMi ? Colors.red : Colors.grey,
                                  size: 20,
                                ),
                              ),
                              const SizedBox(width: 6),
                              Text(
                                '${recipe.begeniSayisi} Beğeni',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: recipe.begendiMi ? Colors.red : Colors.grey.shade700,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),

                      // Yorum Yap Butonu
                      InkWell(
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => RecipeDetailScreen(recipeId: recipe.id, focusComments: true),
                            ),
                          ).then((_) => _loadRecipes());
                        },
                        borderRadius: BorderRadius.circular(8),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          child: Row(
                            children: [
                              const Icon(Icons.mode_comment_outlined, color: Colors.grey, size: 20),
                              const SizedBox(width: 6),
                              Text(
                                '${recipe.yorumSayisi} Yorum',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.grey.shade700,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
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

  // Tarif Görseli Olmadığında Placeholder Gösterici
  Widget _buildPlaceholderImage() {
    return Container(
      color: const Color(0xFFFFEFE5),
      child: const Center(
        child: Text(
          '🍽️',
          style: TextStyle(fontSize: 48),
        ),
      ),
    );
  }
}
