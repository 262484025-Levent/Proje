// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/user_model.dart';
import '../models/recipe_model.dart';
import '../models/comment_model.dart';

class DatabaseService {
  // Singleton pattern
  static final DatabaseService _instance = DatabaseService._internal();
  factory DatabaseService() => _instance;
  DatabaseService._internal();

  // Başarıyla bağlanan son çalışan URL adresi
  String? _workingUrl;

  // Yeni bağlantı oluşturur - Yerel ağdaki çalışan API adresini otomatik keşfeder
  Future<String> _getApiUrl() async {
    if (_workingUrl != null) return _workingUrl!;

    // Taranacak IP/Domain adayları listesi
    final List<String> candidateUrls = [
      'http://10.0.2.2/yemek_web/api.php',   // Android Emulator
      'http://172.20.10.1/yemek_web/api.php', // Hotspot IP (User logs)
      'http://127.0.0.1/yemek_web/api.php',   // iOS / Desktop loopback
      'http://localhost/yemek_web/api.php',
    ];

    // Yaygın yerel alt ağları ekle (192.168.1.X, 192.168.0.X, 192.168.2.X)
    for (int i = 1; i <= 25; i++) {
      candidateUrls.add('http://192.168.1.$i/yemek_web/api.php');
      candidateUrls.add('http://192.168.0.$i/yemek_web/api.php');
      candidateUrls.add('http://192.168.2.$i/yemek_web/api.php');
    }

    final completer = Completer<String>();
    bool found = false;
    int failed = 0;

    for (final url in candidateUrls) {
      http.post(
        Uri.parse(url),
        body: {'islem': 'giris', 'eposta': '', 'sifre': ''},
      ).then((response) {
        if (!found) {
          found = true;
          _workingUrl = url;
          print('Bulunan çalışan API adresi: $url');
          completer.complete(url);
        }
      }).catchError((e) {
        failed++;
        if (failed >= candidateUrls.length && !completer.isCompleted) {
          // Fallback olarak emülatör varsayılanını ata
          _workingUrl = 'http://10.0.2.2/yemek_web/api.php';
          completer.complete('http://10.0.2.2/yemek_web/api.php');
        }
      });
    }

    // Güvenlik amaçlı genel zaman aşımı
    Future.delayed(const Duration(milliseconds: 2000), () {
      if (!completer.isCompleted) {
        _workingUrl = 'http://10.0.2.2/yemek_web/api.php';
        completer.complete('http://10.0.2.2/yemek_web/api.php');
      }
    });

    return completer.future;
  }

  // API'den dönen görsel yollarını tam absolute URL'ye dönüştüren yardımcı metot
  String _resolveImageUrl(String? path, String baseUrl) {
    if (path == null || path.trim().isEmpty) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (path == 'default.png') return 'default.png'; // Arayüzde özel işleniyor

    try {
      final uri = Uri.parse(baseUrl);
      final segments = List<String>.from(uri.pathSegments);
      if (segments.isNotEmpty && segments.last == 'api.php') {
        segments.removeLast();
      }
      final basePath = segments.isEmpty ? '/' : '/${segments.join('/')}/';
      // path parametresi başında slash barındırabilir, temizleyelim
      final cleanPath = path.startsWith('/') ? path.substring(1) : path;
      final resolvedUri = uri.replace(path: basePath + cleanPath);
      return resolvedUri.toString();
    } catch (e) {
      print('URL çözme hatası: $e');
      return path;
    }
  }

  // Bağlantıyı test etmek için yardımcı metod
  Future<bool> testConnection() async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'tarifleri_getir',
          'arama': '',
          'giris_yapan_kullanici_id': '0',
        },
      );
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['basarili'] == true;
      }
      return false;
    } catch (e) {
      print('API bağlantı hatası: $e');
      return false;
    }
  }

  // ==========================================
  // KULLANICI İŞLEMLERİ (AUTH)
  // ==========================================

  // Kullanıcı Girişi
  Future<UserModel?> login(String email, String password) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'giris',
          'eposta': email.trim(),
          'sifre': password,
        },
      );
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['kullanici'] != null) {
          Map<String, dynamic> userMap = Map<String, dynamic>.from(data['kullanici']);
          if (userMap['profil_resmi'] != null) {
            userMap['profil_resmi'] = _resolveImageUrl(userMap['profil_resmi']?.toString(), url);
          }
          return UserModel.fromMap(userMap);
        }
      }
      return null;
    } catch (e) {
      print('Giriş hatası: $e');
      return null;
    }
  }

  // Kullanıcı Kaydı
  Future<UserModel> register(String username, String email, String password) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'kayit',
          'kullanici_adi': username.trim(),
          'eposta': email.trim(),
          'sifre': password,
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['kullanici'] != null) {
          Map<String, dynamic> userMap = Map<String, dynamic>.from(data['kullanici']);
          if (userMap['profil_resmi'] != null) {
            userMap['profil_resmi'] = _resolveImageUrl(userMap['profil_resmi']?.toString(), url);
          }
          return UserModel.fromMap(userMap);
        } else {
          throw Exception(data['hata'] ?? 'Kayıt başarısız.');
        }
      }
      throw Exception('Sunucuyla bağlantı kurulamadı.');
    } catch (e) {
      print('Kayıt hatası: $e');
      rethrow;
    }
  }

  // ==========================================
  // TARİF İŞLEMLERİ
  // ==========================================

  // Tarif Listesi (Akış)
  Future<List<RecipeModel>> getRecipes({String search = '', required int loggedInUserId}) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'tarifleri_getir',
          'arama': search.trim(),
          'giris_yapan_kullanici_id': loggedInUserId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['tarifler'] != null) {
          final List list = data['tarifler'];
          return list.map((map) {
            Map<String, dynamic> recipeMap = Map<String, dynamic>.from(map);
            if (recipeMap['resim_url'] != null) {
              recipeMap['resim_url'] = _resolveImageUrl(recipeMap['resim_url']?.toString(), url);
            }
            if (recipeMap['yazar_resmi'] != null) {
              recipeMap['yazar_resmi'] = _resolveImageUrl(recipeMap['yazar_resmi']?.toString(), url);
            }
            return RecipeModel.fromMap(recipeMap);
          }).toList();
        }
      }
      return [];
    } catch (e) {
      print('Tarifleri çekme hatası: $e');
      return [];
    }
  }

  // En Yüksek Puanlı Tarifler (Top Rated)
  Future<List<RecipeModel>> getTopRatedRecipes({required int loggedInUserId, int limit = 10}) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'en_iyileri_getir',
          'giris_yapan_kullanici_id': loggedInUserId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['tarifler'] != null) {
          final List list = data['tarifler'];
          return list.map((map) {
            Map<String, dynamic> recipeMap = Map<String, dynamic>.from(map);
            if (recipeMap['resim_url'] != null) {
              recipeMap['resim_url'] = _resolveImageUrl(recipeMap['resim_url']?.toString(), url);
            }
            if (recipeMap['yazar_resmi'] != null) {
              recipeMap['yazar_resmi'] = _resolveImageUrl(recipeMap['yazar_resmi']?.toString(), url);
            }
            return RecipeModel.fromMap(recipeMap);
          }).toList();
        }
      }
      return [];
    } catch (e) {
      print('En yüksek puanlı tarifleri alma hatası: $e');
      return [];
    }
  }

  // Kullanıcı Tariflerini Getir
  Future<List<RecipeModel>> getUserRecipes(int userId, {required int loggedInUserId}) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'kullanici_tariflerini_getir',
          'kullanici_id': userId.toString(),
          'giris_yapan_kullanici_id': loggedInUserId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['tarifler'] != null) {
          final List list = data['tarifler'];
          return list.map((map) {
            Map<String, dynamic> recipeMap = Map<String, dynamic>.from(map);
            if (recipeMap['resim_url'] != null) {
              recipeMap['resim_url'] = _resolveImageUrl(recipeMap['resim_url']?.toString(), url);
            }
            if (recipeMap['yazar_resmi'] != null) {
              recipeMap['yazar_resmi'] = _resolveImageUrl(recipeMap['yazar_resmi']?.toString(), url);
            }
            return RecipeModel.fromMap(recipeMap);
          }).toList();
        }
      }
      return [];
    } catch (e) {
      print('Kullanıcı tariflerini alma hatası: $e');
      return [];
    }
  }

  // Tekil Tarif Detayları (Güncel Bilgiyle)
  Future<RecipeModel?> getRecipeDetails(int recipeId, {required int loggedInUserId}) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'tarif_detaylarini_getir',
          'tarif_id': recipeId.toString(),
          'giris_yapan_kullanici_id': loggedInUserId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['tarif'] != null) {
          Map<String, dynamic> recipeMap = Map<String, dynamic>.from(data['tarif']);
          if (recipeMap['resim_url'] != null) {
            recipeMap['resim_url'] = _resolveImageUrl(recipeMap['resim_url']?.toString(), url);
          }
          if (recipeMap['yazar_resmi'] != null) {
            recipeMap['yazar_resmi'] = _resolveImageUrl(recipeMap['yazar_resmi']?.toString(), url);
          }
          return RecipeModel.fromMap(recipeMap);
        }
      }
      return null;
    } catch (e) {
      print('Tarif detay alma hatası: $e');
      return null;
    }
  }

  // Tarif Ekleme (Yerel dosya yolu desteğiyle Multipart)
  Future<int?> addRecipe({
    required int userId,
    required String title,
    required String description,
    required String ingredients,
    required String instructions,
    int? prepTime,
    int? cookTime,
    required int servings,
    String? imageUrl,
    String? localImagePath,
  }) async {
    try {
      final url = await _getApiUrl();

      // Eğer lokal fotoğraf seçilmişse Multipart ile gönderelim
      if (localImagePath != null && localImagePath.isNotEmpty) {
        final request = http.MultipartRequest('POST', Uri.parse(url));
        request.fields['islem'] = 'tarif_ekle';
        request.fields['kullanici_id'] = userId.toString();
        request.fields['baslik'] = title.trim();
        request.fields['aciklama'] = description.trim();
        request.fields['malzemeler'] = ingredients.trim();
        request.fields['hazirlanisi'] = instructions.trim();
        request.fields['hazirlama_suresi'] = prepTime?.toString() ?? '';
        request.fields['pisirme_suresi'] = cookTime?.toString() ?? '';
        request.fields['kisi_sayisi'] = servings.toString();
        
        request.files.add(await http.MultipartFile.fromPath(
          'photo',
          localImagePath,
        ));

        final streamedResponse = await request.send();
        final response = await http.Response.fromStream(streamedResponse);

        if (response.statusCode == 200) {
          final data = json.decode(response.body);
          if (data['basarili'] == true && data['eklenen_id'] != null) {
            return data['eklenen_id'] as int;
          }
        }
      } else {
        // Lokal fotoğraf yoksa klasik POST ile gönderelim
        final response = await http.post(
          Uri.parse(url),
          body: {
            'islem': 'tarif_ekle',
            'kullanici_id': userId.toString(),
            'baslik': title.trim(),
            'aciklama': description.trim(),
            'malzemeler': ingredients.trim(),
            'hazirlanisi': instructions.trim(),
            'hazirlama_suresi': prepTime?.toString() ?? '',
            'pisirme_suresi': cookTime?.toString() ?? '',
            'kisi_sayisi': servings.toString(),
            'resim_url': imageUrl?.trim() ?? '',
          },
        );

        if (response.statusCode == 200) {
          final data = json.decode(response.body);
          if (data['basarili'] == true && data['eklenen_id'] != null) {
            return data['eklenen_id'] as int;
          }
        }
      }
      return null;
    } catch (e) {
      print('Tarif ekleme hatası: $e');
      rethrow;
    }
  }

  // Tarif Silme
  Future<bool> deleteRecipe(int recipeId, int userId) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'tarif_sil',
          'tarif_id': recipeId.toString(),
          'kullanici_id': userId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        return data['basarili'] == true;
      }
      return false;
    } catch (e) {
      print('Tarif silme hatası: $e');
      return false;
    }
  }

  // ==========================================
  // BEĞENİ VE PUANLAMA İŞLEMLERİ
  // ==========================================

  // Beğeni Durumunu Değiştirme (Like / Unlike)
  Future<Map<String, dynamic>> toggleLike(int userId, int recipeId) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'begeni_durumunu_degistir',
          'kullanici_id': userId.toString(),
          'tarif_id': recipeId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true) {
          return {
            'begendiMi': data['begendi_mi'] as bool,
            'begeniSayisi': data['begeni_sayisi'] as int,
          };
        }
      }
      throw Exception('Beğeni işlemi başarısız.');
    } catch (e) {
      print('Beğeni değiştirme hatası: $e');
      rethrow;
    }
  }

  // Tarif Puanlama (1-5 Yıldız)
  Future<Map<String, dynamic>> rateRecipe(int userId, int recipeId, int rating) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'tarif_puanla',
          'kullanici_id': userId.toString(),
          'tarif_id': recipeId.toString(),
          'puan': rating.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true) {
          return {
            'ortalamaPuan': (data['ortalama_puan'] as num).toDouble(),
            'puanSayisi': data['puan_sayisi'] as int,
          };
        }
      }
      throw Exception('Puanlama işlemi başarısız.');
    } catch (e) {
      print('Puanlama hatası: $e');
      rethrow;
    }
  }

  // Kullanıcının bir tarife verdiği puanı getirme
  Future<int> getUserRecipeRating(int userId, int recipeId) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'kullanici_puanini_getir',
          'kullanici_id': userId.toString(),
          'tarif_id': recipeId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true) {
          return data['puan'] as int;
        }
      }
      return 0;
    } catch (e) {
      print('Kullanıcı puanını getirme hatası: $e');
      return 0;
    }
  }

  // ==========================================
  // YORUM İŞLEMLERİ
  // ==========================================

  // Tarife Ait Yorumları Getir
  Future<List<CommentModel>> getComments(int recipeId) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'yorumlari_getir',
          'tarif_id': recipeId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['yorumlar'] != null) {
          final List list = data['yorumlar'];
          return list.map((map) {
            Map<String, dynamic> commentMap = Map<String, dynamic>.from(map);
            if (commentMap['yazar_resmi'] != null) {
              commentMap['yazar_resmi'] = _resolveImageUrl(commentMap['yazar_resmi']?.toString(), url);
            }
            // Yanıtları da (cevaplar) tam avatar URL'leriyle çözümleyelim
            if (commentMap['cevaplar'] != null && commentMap['cevaplar'] is List) {
              final List reps = commentMap['cevaplar'];
              commentMap['cevaplar'] = reps.map((rep) {
                Map<String, dynamic> repMap = Map<String, dynamic>.from(rep);
                if (repMap['yazar_resmi'] != null) {
                  repMap['yazar_resmi'] = _resolveImageUrl(repMap['yazar_resmi']?.toString(), url);
                }
                return repMap;
              }).toList();
            }
            return CommentModel.fromMap(commentMap);
          }).toList();
        }
      }
      return [];
    } catch (e) {
      print('Yorumları alma hatası: $e');
      return [];
    }
  }

  // Yorum Ekleme
  Future<CommentModel?> addComment(int userId, int recipeId, String content) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'yorum_ekle',
          'kullanici_id': userId.toString(),
          'tarif_id': recipeId.toString(),
          'icerik': content.trim(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['yorum'] != null) {
          Map<String, dynamic> commentMap = Map<String, dynamic>.from(data['yorum']);
          if (commentMap['yazar_resmi'] != null) {
            commentMap['yazar_resmi'] = _resolveImageUrl(commentMap['yazar_resmi']?.toString(), url);
          }
          return CommentModel.fromMap(commentMap);
        }
      }
      return null;
    } catch (e) {
      print('Yorum ekleme hatası: $e');
      rethrow;
    }
  }

  // Yorum Yanıtı (Yoruma yorum) Ekleme
  Future<CommentReplyModel?> addCommentReply(
    int userId,
    int commentId,
    String content, {
    int? parentReplyId,
  }) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'yorum_cevap_ekle',
          'kullanici_id': userId.toString(),
          'yorum_id': commentId.toString(),
          'icerik': content.trim(),
          'ust_cevap_id': parentReplyId?.toString() ?? '',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['cevap'] != null) {
          Map<String, dynamic> replyMap = Map<String, dynamic>.from(data['cevap']);
          if (replyMap['yazar_resmi'] != null) {
            replyMap['yazar_resmi'] = _resolveImageUrl(replyMap['yazar_resmi']?.toString(), url);
          }
          return CommentReplyModel.fromMap(replyMap);
        }
      }
      return null;
    } catch (e) {
      print('Yorum yanıtı ekleme hatası: $e');
      rethrow;
    }
  }

  // ==========================================
  // PROFİL GÜNCELLEME İŞLEMLERİ
  // ==========================================

  // Kullanıcı Bilgilerini Çekme
  Future<UserModel?> getUserProfile(int userId) async {
    try {
      final url = await _getApiUrl();
      final response = await http.post(
        Uri.parse(url),
        body: {
          'islem': 'kullanici_profilini_getir',
          'kullanici_id': userId.toString(),
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['basarili'] == true && data['kullanici'] != null) {
          Map<String, dynamic> userMap = Map<String, dynamic>.from(data['kullanici']);
          if (userMap['profil_resmi'] != null) {
            userMap['profil_resmi'] = _resolveImageUrl(userMap['profil_resmi']?.toString(), url);
          }
          return UserModel.fromMap(userMap);
        }
      }
      return null;
    } catch (e) {
      print('Profil çekme hatası: $e');
      return null;
    }
  }

  // Profil Güncelleme (Lokal dosya yolu desteğiyle Multipart)
  Future<UserModel?> updateProfile({
    required int userId,
    required String username,
    required String bio,
    String? avatar,
    String? localAvatarPath,
  }) async {
    try {
      final url = await _getApiUrl();

      // Eğer lokal avatar görseli seçilmişse Multipart ile gönderelim
      if (localAvatarPath != null && localAvatarPath.isNotEmpty) {
        final request = http.MultipartRequest('POST', Uri.parse(url));
        request.fields['islem'] = 'profil_guncelle';
        request.fields['kullanici_id'] = userId.toString();
        request.fields['kullanici_adi'] = username.trim();
        request.fields['biyografi'] = bio.trim();

        request.files.add(await http.MultipartFile.fromPath(
          'photo',
          localAvatarPath,
        ));

        final streamedResponse = await request.send();
        final response = await http.Response.fromStream(streamedResponse);

        if (response.statusCode == 200) {
          final data = json.decode(response.body);
          if (data['basarili'] == true && data['kullanici'] != null) {
            Map<String, dynamic> userMap = Map<String, dynamic>.from(data['kullanici']);
            if (userMap['profil_resmi'] != null) {
              userMap['profil_resmi'] = _resolveImageUrl(userMap['profil_resmi']?.toString(), url);
            }
            return UserModel.fromMap(userMap);
          } else {
            throw Exception(data['hata'] ?? 'Profil güncellenemedi.');
          }
        }
      } else {
        // Yoksa klasik POST ile devam et
        final response = await http.post(
          Uri.parse(url),
          body: {
            'islem': 'profil_guncelle',
            'kullanici_id': userId.toString(),
            'kullanici_adi': username.trim(),
            'biyografi': bio.trim(),
            'profil_resmi': avatar?.trim() ?? '',
          },
        );

        if (response.statusCode == 200) {
          final data = json.decode(response.body);
          if (data['basarili'] == true && data['kullanici'] != null) {
            Map<String, dynamic> userMap = Map<String, dynamic>.from(data['kullanici']);
            if (userMap['profil_resmi'] != null) {
              userMap['profil_resmi'] = _resolveImageUrl(userMap['profil_resmi']?.toString(), url);
            }
            return UserModel.fromMap(userMap);
          } else {
            throw Exception(data['hata'] ?? 'Profil güncellenemedi.');
          }
        }
      }
      throw Exception('Sunucuyla bağlantı kurulamadı.');
    } catch (e) {
      print('Profil güncelleme hatası: $e');
      rethrow;
    }
  }
}
