// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'package:flutter/material.dart';
import '../services/database_service.dart';
import '../services/auth_controller.dart';
import '../models/recipe_model.dart';
import '../models/comment_model.dart';

class RecipeDetailScreen extends StatefulWidget {
  final int recipeId;
  final bool focusComments;

  const RecipeDetailScreen({
    super.key,
    required this.recipeId,
    this.focusComments = false,
  });

  @override
  State<RecipeDetailScreen> createState() => _RecipeDetailScreenState();
}

class _RecipeDetailScreenState extends State<RecipeDetailScreen> {
  final DatabaseService _dbService = DatabaseService();
  final AuthController _authController = AuthController();
  final TextEditingController _commentController = TextEditingController();
  final FocusNode _commentFocusNode = FocusNode();

  RecipeModel? _recipe;
  List<CommentModel> _comments = [];
  bool _isLoading = true;
  int _userRating = 0;
  bool _isSubmittingComment = false;
  CommentModel? _replyingToComment; // Yeni yorum yanıtı takibi

  @override
  void initState() {
    super.initState();
    _loadRecipeDetails();
    if (widget.focusComments) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _commentFocusNode.requestFocus();
      });
    }
  }

  @override
  void dispose() {
    _commentController.dispose();
    _commentFocusNode.dispose();
    super.dispose();
  }

  // Detay Verilerini Yükle
  Future<void> _loadRecipeDetails() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final userId = _authController.currentUser?.id ?? 0;
      final recipe = await _dbService.getRecipeDetails(widget.recipeId, loggedInUserId: userId);
      final comments = await _dbService.getComments(widget.recipeId);
      
      int userRating = 0;
      if (userId != 0) {
        userRating = await _dbService.getUserRecipeRating(userId, widget.recipeId);
      }

      if (mounted) {
        setState(() {
          _recipe = recipe;
          _comments = comments;
          _userRating = userRating;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Detay yükleme hatası: $e');
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // Beğeni Butonu
  Future<void> _toggleLike() async {
    if (_recipe == null) return;
    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0) return;

    final isLikedNow = !_recipe!.begendiMi;
    final updatedLikeCount = isLikedNow ? _recipe!.begeniSayisi + 1 : _recipe!.begeniSayisi - 1;

    setState(() {
      _recipe = _recipe!.copyWith(
        begendiMi: isLikedNow,
        begeniSayisi: updatedLikeCount,
      );
    });

    try {
      await _dbService.toggleLike(userId, _recipe!.id);
    } catch (e) {
      print('Beğeni hatası: $e');
      _loadRecipeDetails();
    }
  }

  // İnteraktif Yıldız Puanlama
  Future<void> _rateRecipe(int rating) async {
    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0 || _recipe == null) return;

    setState(() {
      _userRating = rating;
    });

    try {
      final stats = await _dbService.rateRecipe(userId, _recipe!.id, rating);
      
      setState(() {
        _recipe = _recipe!.copyWith(
          ortalamaPuan: stats['ortalamaPuan'],
          puanSayisi: stats['puanSayisi'],
        );
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Puanınız ($rating Yıldız) başarıyla kaydedildi!'),
            backgroundColor: Colors.green,
            duration: const Duration(seconds: 1),
          ),
        );
      }
    } catch (e) {
      print('Puan verme hatası: $e');
    }
  }

  // Yeni Yorum / Yanıt Ekleme
  Future<void> _submitComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty) return;

    final userId = _authController.currentUser?.id ?? 0;
    if (userId == 0 || _recipe == null) return;

    setState(() {
      _isSubmittingComment = true;
    });

    try {
      if (_replyingToComment != null) {
        // Yorum yanıtı (Yoruma yorum)
        final newReply = await _dbService.addCommentReply(
          userId,
          _replyingToComment!.id,
          content,
        );
        if (newReply != null) {
          setState(() {
            _commentController.clear();
            _replyingToComment = null;
          });
          _commentFocusNode.unfocus();
        }
      } else {
        // Normal yorum ekleme
        final newComment = await _dbService.addComment(userId, _recipe!.id, content);
        if (newComment != null) {
          setState(() {
            _comments.insert(0, newComment);
            _commentController.clear();
          });
          _commentFocusNode.unfocus();
        }
      }
    } catch (e) {
      print('Yorum ekleme hatası: $e');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Yorum eklenemedi.'), backgroundColor: Colors.red),
      );
    } finally {
      setState(() {
        _isSubmittingComment = false;
      });
      _loadRecipeDetails(); // Detayları ve sayaçları yeniden yükle
    }
  }

  // Tarifi Sil (Sadece yazar silebilir)
  Future<void> _deleteRecipe() async {
    if (_recipe == null) return;
    final userId = _authController.currentUser?.id ?? 0;
    
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tarifi Sil'),
        content: const Text('Bu tarifi silmek istediğinize emin misiniz? Bu işlem geri alınamaz.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Vazgeç', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Sil', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm == true) {
      final success = await _dbService.deleteRecipe(_recipe!.id, userId);
      if (success && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Tarif başarıyla silindi.'), backgroundColor: Colors.green),
        );
        Navigator.pop(context);
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Tarif silinemedi.'), backgroundColor: Colors.red),
          );
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentUserId = _authController.currentUser?.id ?? 0;

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        title: Text(
          _recipe?.baslik ?? 'Tarif Detayı',
          style: const TextStyle(color: Color(0xFF1E2022), fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFFFF7E36)),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          if (_recipe != null && _recipe!.kullaniciId == currentUserId)
            IconButton(
              icon: const Icon(Icons.delete_outline, color: Colors.red),
              tooltip: 'Tarifi Sil',
              onPressed: _deleteRecipe,
            ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFFF7E36)))
          : _recipe == null
              ? const Center(child: Text('Tarif bulunamadı.'))
              : Column(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.only(bottom: 24),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            // 1. Tarif Görseli
                            AspectRatio(
                              aspectRatio: 16 / 10,
                              child: (_recipe!.resimUrl != null && _recipe!.resimUrl!.startsWith('http'))
                                  ? Image.network(_recipe!.resimUrl!, fit: BoxFit.cover, errorBuilder: (c, o, s) => _buildPlaceholder())
                                  : _buildPlaceholder(),
                            ),

                            Padding(
                              padding: const EdgeInsets.all(16.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // 2. Yazar Bilgisi
                                  Row(
                                    children: [
                                      CircleAvatar(
                                        radius: 20,
                                        backgroundColor: const Color(0xFFFFEFE5),
                                        backgroundImage: (_recipe!.yazarResmi != null && _recipe!.yazarResmi != 'default.png')
                                            ? NetworkImage(_recipe!.yazarResmi!)
                                            : null,
                                        child: (_recipe!.yazarResmi == null || _recipe!.yazarResmi == 'default.png')
                                            ? Text(
                                                _recipe!.yazarAdi != null && _recipe!.yazarAdi!.isNotEmpty
                                                    ? _recipe!.yazarAdi![0].toUpperCase()
                                                    : 'Y',
                                                style: const TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold),
                                              )
                                            : null,
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              '@${_recipe!.yazarAdi ?? "anonim"}',
                                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF1E2022)),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              '${_recipe!.olusturulmaTarihi.day}.${_recipe!.olusturulmaTarihi.month}.${_recipe!.olusturulmaTarihi.year}',
                                              style: const TextStyle(fontSize: 11, color: Colors.grey),
                                            ),
                                          ],
                                        ),
                                      ),
                                      
                                      // Like Butonu
                                      IconButton(
                                        icon: Icon(
                                          _recipe!.begendiMi ? Icons.favorite : Icons.favorite_border,
                                          color: _recipe!.begendiMi ? Colors.red : Colors.grey,
                                          size: 26,
                                        ),
                                        onPressed: _toggleLike,
                                      ),
                                      Text(
                                        '${_recipe!.begeniSayisi}',
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 16),

                                  // 3. Tarif Başlığı ve Puanlama Bilgisi
                                  Text(
                                    _recipe!.baslik,
                                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: Color(0xFF1E2022), letterSpacing: -0.5),
                                  ),
                                  const SizedBox(height: 8),

                                  // Puanlama Detayı
                                  Row(
                                    children: [
                                      const Icon(Icons.star, color: Color(0xFFFFB300), size: 20),
                                      const SizedBox(width: 4),
                                      Text(
                                        _recipe!.ortalamaPuan.toStringAsFixed(1),
                                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                      ),
                                      const SizedBox(width: 6),
                                      Text(
                                        '(${_recipe!.puanSayisi} Değerlendirme)',
                                        style: const TextStyle(fontSize: 13, color: Colors.grey),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 16),

                                  // 4. Hazırlık ve Pişirme Süresi Çipleri
                                  Row(
                                    children: [
                                      _buildInfoChip(Icons.timer_outlined, '${_recipe!.hazirlamaSuresi ?? 0} dk Hazırlık'),
                                      const SizedBox(width: 12),
                                      _buildInfoChip(Icons.local_fire_department_outlined, '${_recipe!.pisirmeSuresi ?? 0} dk Pişirme'),
                                      const SizedBox(width: 12),
                                      _buildInfoChip(Icons.restaurant_outlined, '${_recipe!.kisiSayisi} Porsiyon'),
                                    ],
                                  ),
                                  const SizedBox(height: 16),

                                  if (_recipe!.aciklama != null && _recipe!.aciklama!.trim().isNotEmpty) ...[
                                    Text(
                                      _recipe!.aciklama!,
                                      style: TextStyle(fontSize: 14, color: Colors.grey.shade700, height: 1.5),
                                    ),
                                    const SizedBox(height: 20),
                                  ],

                                  // 5. İnteraktif Puanlama Yıldızları
                                  Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFAFAFA),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: Colors.grey.shade200),
                                    ),
                                    child: Column(
                                      children: [
                                        const Text(
                                          'Bu tarifi puanlayın!',
                                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1E2022)),
                                        ),
                                        const SizedBox(height: 8),
                                        Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: List.generate(5, (index) {
                                            final starValue = index + 1;
                                            return GestureDetector(
                                              onTap: () => _rateRecipe(starValue),
                                              child: Padding(
                                                padding: const EdgeInsets.symmetric(horizontal: 6),
                                                child: Icon(
                                                  starValue <= _userRating ? Icons.star : Icons.star_border,
                                                  color: const Color(0xFFFFB300),
                                                  size: 32,
                                                ),
                                              ),
                                            );
                                          }),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 24),

                                  // 6. Malzemeler (Ingredients)
                                  const Text(
                                    'Malzemeler',
                                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E2022)),
                                  ),
                                  const SizedBox(height: 8),
                                  ..._parseMultilineList(_recipe!.malzemeler).map((ing) => Padding(
                                        padding: const EdgeInsets.symmetric(vertical: 4),
                                        child: Row(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Padding(
                                              padding: EdgeInsets.only(top: 6),
                                              child: Icon(Icons.circle, size: 6, color: Color(0xFFFF7E36)),
                                            ),
                                            const SizedBox(width: 8),
                                            Expanded(
                                              child: Text(
                                                ing,
                                                style: const TextStyle(fontSize: 14, height: 1.4),
                                              ),
                                            ),
                                          ],
                                        ),
                                      )),
                                  const SizedBox(height: 24),

                                  // 7. Hazırlanışı (Instructions)
                                  const Text(
                                    'Nasıl Yapılır?',
                                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E2022)),
                                  ),
                                  const SizedBox(height: 8),
                                  ..._parseMultilineList(_recipe!.hazirlanisi).asMap().entries.map((entry) {
                                    final stepNum = entry.key + 1;
                                    final stepText = entry.value;
                                    return Padding(
                                      padding: const EdgeInsets.symmetric(vertical: 8),
                                      child: Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Container(
                                            width: 24,
                                            height: 24,
                                            decoration: const BoxDecoration(
                                              color: Color(0xFFFFEFE5),
                                              shape: BoxShape.circle,
                                            ),
                                            child: Center(
                                              child: Text(
                                                '$stepNum',
                                                style: const TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 12),
                                              ),
                                            ),
                                          ),
                                          const SizedBox(width: 10),
                                          Expanded(
                                            child: Text(
                                              stepText,
                                              style: const TextStyle(fontSize: 14, height: 1.5),
                                            ),
                                          ),
                                        ],
                                      ),
                                    );
                                  }),
                                  const SizedBox(height: 28),

                                  // 8. Yorumlar Listesi
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      const Text(
                                        'Yorumlar',
                                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF1E2022)),
                                      ),
                                      Text(
                                        '${_comments.length} Yorum',
                                        style: const TextStyle(fontSize: 13, color: Colors.grey, fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),

                                  _comments.isEmpty
                                      ? const Padding(
                                          padding: EdgeInsets.symmetric(vertical: 16),
                                          child: Center(
                                            child: Text(
                                              'Henüz yorum yapılmamış. İlk yorumu siz yazın!',
                                              style: TextStyle(color: Colors.grey, fontSize: 13),
                                            ),
                                          ),
                                        )
                                      : ListView.builder(
                                          shrinkWrap: true,
                                          physics: const NeverScrollableScrollPhysics(),
                                          itemCount: _comments.length,
                                          itemBuilder: (context, index) {
                                            final comment = _comments[index];
                                            return Container(
                                              margin: const EdgeInsets.only(bottom: 12),
                                              padding: const EdgeInsets.all(12),
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFFBFBFB),
                                                borderRadius: BorderRadius.circular(12),
                                                border: Border.all(color: Colors.grey.shade100),
                                              ),
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Row(
                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                    children: [
                                                      CircleAvatar(
                                                        radius: 16,
                                                        backgroundColor: const Color(0xFFFFEFE5),
                                                        backgroundImage: (comment.yazarResmi != null && comment.yazarResmi != 'default.png')
                                                            ? NetworkImage(comment.yazarResmi!)
                                                            : null,
                                                        child: (comment.yazarResmi == null || comment.yazarResmi == 'default.png')
                                                            ? Text(
                                                                comment.yazarAdi != null && comment.yazarAdi!.isNotEmpty
                                                                    ? comment.yazarAdi![0].toUpperCase()
                                                                    : 'Y',
                                                                style: const TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 11),
                                                              )
                                                            : null,
                                                      ),
                                                      const SizedBox(width: 8),
                                                      Expanded(
                                                        child: Column(
                                                          crossAxisAlignment: CrossAxisAlignment.start,
                                                          children: [
                                                            Row(
                                                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                              children: [
                                                                Text(
                                                                  '@${comment.yazarAdi ?? "anonim"}',
                                                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1E2022)),
                                                                ),
                                                                Text(
                                                                  '${comment.olusturulmaTarihi.day}.${comment.olusturulmaTarihi.month}.${comment.olusturulmaTarihi.year}',
                                                                  style: const TextStyle(fontSize: 10, color: Colors.grey),
                                                                ),
                                                              ],
                                                            ),
                                                            const SizedBox(height: 4),
                                                            Text(
                                                              comment.icerik,
                                                              style: const TextStyle(fontSize: 13, color: Color(0xFF2C2C2C), height: 1.4),
                                                            ),
                                                          ],
                                                        ),
                                                      ),
                                                    ],
                                                  ),
                                                  
                                                  // Cevapla Butonu (Sağ Altta)
                                                  Padding(
                                                    padding: const EdgeInsets.only(left: 40, top: 4),
                                                    child: TextButton.icon(
                                                      onPressed: () {
                                                        setState(() {
                                                          _replyingToComment = comment;
                                                        });
                                                        _commentFocusNode.requestFocus();
                                                      },
                                                      icon: const Icon(Icons.reply, size: 14, color: Color(0xFFFF7E36)),
                                                      label: const Text(
                                                        'Cevapla',
                                                        style: TextStyle(fontSize: 11, color: Color(0xFFFF7E36), fontWeight: FontWeight.bold),
                                                      ),
                                                      style: TextButton.styleFrom(
                                                        padding: EdgeInsets.zero,
                                                        minimumSize: const Size(0, 0),
                                                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                                      ),
                                                    ),
                                                  ),

                                                  // Alt Cevaplar (Girintili Yanıtlar)
                                                  if (comment.cevaplar.isNotEmpty) ...[
                                                    const SizedBox(height: 8),
                                                    Padding(
                                                      padding: const EdgeInsets.only(left: 40),
                                                      child: Column(
                                                        children: comment.cevaplar.map((reply) {
                                                          return Container(
                                                            margin: const EdgeInsets.only(top: 8),
                                                            padding: const EdgeInsets.all(8),
                                                            decoration: BoxDecoration(
                                                              color: const Color(0xFFF3F3F3),
                                                              borderRadius: BorderRadius.circular(8),
                                                              border: Border.all(color: Colors.grey.shade200),
                                                            ),
                                                            child: Row(
                                                              crossAxisAlignment: CrossAxisAlignment.start,
                                                              children: [
                                                                CircleAvatar(
                                                                  radius: 12,
                                                                  backgroundColor: const Color(0xFFFFEFE5),
                                                                  backgroundImage: (reply.yazarResmi != null && reply.yazarResmi != 'default.png')
                                                                      ? NetworkImage(reply.yazarResmi!)
                                                                      : null,
                                                                  child: (reply.yazarResmi == null || reply.yazarResmi == 'default.png')
                                                                      ? Text(
                                                                          reply.yazarAdi != null && reply.yazarAdi!.isNotEmpty
                                                                              ? reply.yazarAdi![0].toUpperCase()
                                                                              : 'Y',
                                                                          style: const TextStyle(color: Color(0xFFFF7E36), fontWeight: FontWeight.bold, fontSize: 8),
                                                                        )
                                                                      : null,
                                                                ),
                                                                const SizedBox(width: 8),
                                                                Expanded(
                                                                  child: Column(
                                                                    crossAxisAlignment: CrossAxisAlignment.start,
                                                                    children: [
                                                                      Row(
                                                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                                        children: [
                                                                          Text(
                                                                            '@${reply.yazarAdi ?? "anonim"}',
                                                                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11, color: Color(0xFF1E2022)),
                                                                          ),
                                                                          Text(
                                                                            '${reply.olusturulmaTarihi.day}.${reply.olusturulmaTarihi.month}.${reply.olusturulmaTarihi.year}',
                                                                            style: const TextStyle(fontSize: 8, color: Colors.grey),
                                                                          ),
                                                                        ],
                                                                      ),
                                                                      const SizedBox(height: 2),
                                                                      Text(
                                                                        reply.icerik,
                                                                        style: const TextStyle(fontSize: 12, color: Color(0xFF2C2C2C), height: 1.3),
                                                                      ),
                                                                    ],
                                                                  ),
                                                                ),
                                                              ],
                                                            ),
                                                          );
                                                        }).toList(),
                                                      ),
                                                    ),
                                                  ],
                                                ],
                                              ),
                                            );
                                          },
                                        ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Yorum Ekleme Çubuğu (Sabit Alt Bar)
                    Container(
                      padding: EdgeInsets.only(
                        left: 16,
                        right: 16,
                        top: 10,
                        bottom: MediaQuery.of(context).viewInsets.bottom + 12,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.05),
                            blurRadius: 10,
                            offset: const Offset(0, -4),
                          )
                        ],
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (_replyingToComment != null)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                              margin: const EdgeInsets.only(bottom: 8),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFEFE5),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      '@${_replyingToComment!.yazarAdi ?? "anonim"} kullanıcısına yanıt yazıyorsunuz...',
                                      style: const TextStyle(fontSize: 12, color: Color(0xFFFF7E36), fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                  GestureDetector(
                                    onTap: () {
                                      setState(() {
                                        _replyingToComment = null;
                                      });
                                    },
                                    child: const Icon(Icons.cancel, size: 18, color: Color(0xFFFF7E36)),
                                  ),
                                ],
                              ),
                            ),
                          Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _commentController,
                                  focusNode: _commentFocusNode,
                                  decoration: InputDecoration(
                                    hintText: _replyingToComment != null ? 'Yanıtınızı buraya yazın...' : 'Yorumunuzu buraya yazın...',
                                    hintStyle: const TextStyle(fontSize: 14),
                                    filled: true,
                                    fillColor: const Color(0xFFF3F3F3),
                                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                                    border: OutlineInputBorder(
                                      borderRadius: BorderRadius.circular(24),
                                      borderSide: BorderSide.none,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              _isSubmittingComment
                                  ? const SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: CircularProgressIndicator(color: Color(0xFFFF7E36), strokeWidth: 2),
                                    )
                                  : IconButton(
                                      icon: const Icon(Icons.send, color: Color(0xFFFF7E36)),
                                      onPressed: _submitComment,
                                    ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }

  // Bilgi Çipi
  Widget _buildInfoChip(IconData icon, String text) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
        decoration: BoxDecoration(
          color: const Color(0xFFFAFAFA),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.grey.shade200),
        ),
        child: Column(
          children: [
            Icon(icon, color: const Color(0xFFFF7E36), size: 20),
            const SizedBox(height: 4),
            Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFF1E2022)),
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
        child: Text('🍽️', style: TextStyle(fontSize: 64)),
      ),
    );
  }

  // Satırlara bölünmüş listeyi ayrıştırır
  List<String> _parseMultilineList(String data) {
    if (data.trim().isEmpty) return [];
    return data
        .split('\n')
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
  }
}
