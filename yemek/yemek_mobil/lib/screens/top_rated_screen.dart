// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'package:flutter/material.dart';
import '../services/database_service.dart';
import '../services/auth_controller.dart';
import '../models/recipe_model.dart';
import 'recipe_detail_screen.dart';

class TopRatedScreen extends StatefulWidget {
  const TopRatedScreen({super.key});

  @override
  State<TopRatedScreen> createState() => _TopRatedScreenState();
}

class _TopRatedScreenState extends State<TopRatedScreen> {
  final DatabaseService _dbService = DatabaseService();
  final AuthController _authController = AuthController();

  List<RecipeModel> _topRecipes = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadTopRecipes();
  }

  // En İyi Tarifleri Yükle
  Future<void> _loadTopRecipes() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final userId = _authController.currentUser?.id ?? 0;
      final recipes = await _dbService.getTopRatedRecipes(loggedInUserId: userId);
      if (mounted) {
        setState(() {
          _topRecipes = recipes;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('En yüksek puanlıları yükleme hatası: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFAFAFA),
      appBar: AppBar(
        title: const Text(
          'En İyi Tarifler',
          style: TextStyle(color: Color(0xFF1E2022), fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Color(0xFFFF7E36)),
            onPressed: _loadTopRecipes,
          )
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadTopRecipes,
        color: const Color(0xFFFF7E36),
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: Color(0xFFFF7E36)))
            : _topRecipes.isEmpty
                ? ListView(
                    children: [
                      SizedBox(height: MediaQuery.of(context).size.height * 0.25),
                      const Center(child: Text('⭐', style: TextStyle(fontSize: 64))),
                      const SizedBox(height: 16),
                      const Center(
                        child: Text(
                          'Henüz Değerlendirme Yok',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Center(
                        child: Text(
                          'Tarifler puanlandığında en iyiler burada listelenecek.',
                          style: TextStyle(color: Colors.grey, fontSize: 13),
                        ),
                      ),
                    ],
                  )
                : Column(
                    children: [
                      // Leadership Header
                      Container(
                        width: double.infinity,
                        color: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
                        child: Column(
                          children: const [
                            Text(
                              '👑 Tarif Liderlik Tablosu',
                              style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFFFF7E36)),
                            ),
                            SizedBox(height: 4),
                            Text(
                              'Gurmelerimiz tarafından en yüksek yıldız puanı verilen lezzetler.',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 12, color: Colors.grey),
                            ),
                          ],
                        ),
                      ),
                      
                      // Leaderboard list
                      Expanded(
                        child: ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: _topRecipes.length,
                          itemBuilder: (context, index) {
                            final recipe = _topRecipes[index];
                            return _buildLeaderboardItem(recipe, index);
                          },
                        ),
                      ),
                    ],
                  ),
      ),
    );
  }

  // Leaderboard card
  Widget _buildLeaderboardItem(RecipeModel recipe, int index) {
    // Degree icons
    String rankEmoji = '';
    Color rankColor = const Color(0xFF1E2022);
    
    if (index == 0) {
      rankEmoji = '🥇';
      rankColor = const Color(0xFFFFB300); // Gold
    } else if (index == 1) {
      rankEmoji = '🥈';
      rankColor = const Color(0xFF9E9E9E); // Silver
    } else if (index == 2) {
      rankEmoji = '🥉';
      rankColor = const Color(0xFFCD7F32); // Bronze
    } else {
      rankEmoji = '${index + 1}';
    }

    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => RecipeDetailScreen(recipeId: recipe.id),
          ),
        ).then((_) => _loadTopRecipes());
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: index < 3 ? rankColor.withOpacity(0.3) : Colors.transparent,
            width: index < 3 ? 1.5 : 0,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.03),
              blurRadius: 8,
              offset: const Offset(0, 3),
            )
          ],
        ),
        child: Row(
          children: [
            // Ranking number / Emoji
            Container(
              width: 38,
              alignment: Alignment.center,
              child: Text(
                rankEmoji,
                style: TextStyle(
                  fontSize: index < 3 ? 24 : 16,
                  fontWeight: FontWeight.bold,
                  color: Colors.grey.shade700,
                ),
              ),
            ),
            const SizedBox(width: 8),

            // Recipe Image Small
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: SizedBox(
                width: 54,
                height: 54,
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

            // Info
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
                  Text(
                    '@${recipe.yazarAdi ?? "anonim"}',
                    style: const TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                ],
              ),
            ),

            // Puan and Rating count
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Row(
                  children: [
                    const Icon(Icons.star, color: Color(0xFFFFB300), size: 18),
                    const SizedBox(width: 2),
                    Text(
                      recipe.ortalamaPuan.toStringAsFixed(1),
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: Color(0xFF1E2022),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(
                  '${recipe.puanSayisi} oy',
                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPlaceholder() {
    return Container(
      color: const Color(0xFFFFEFE5),
      child: const Center(
        child: Text('🍽️', style: TextStyle(fontSize: 22)),
      ),
    );
  }
}
