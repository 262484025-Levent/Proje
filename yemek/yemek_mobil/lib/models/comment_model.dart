// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

class CommentReplyModel {
  final int id;
  final int kullaniciId;
  final int yorumId;
  final int? ustCevapId;
  final String icerik;
  final DateTime olusturulmaTarihi;
  final String? yazarAdi;
  final String? yazarResmi;

  CommentReplyModel({
    required this.id,
    required this.kullaniciId,
    required this.yorumId,
    this.ustCevapId,
    required this.icerik,
    required this.olusturulmaTarihi,
    this.yazarAdi,
    this.yazarResmi,
  });

  factory CommentReplyModel.fromMap(Map<String, dynamic> map) {
    return CommentReplyModel(
      id: map['id'] is int ? map['id'] : int.parse(map['id'].toString()),
      kullaniciId: map['kullanici_id'] is int ? map['kullanici_id'] : int.parse(map['kullanici_id'].toString()),
      yorumId: map['yorum_id'] is int ? map['yorum_id'] : int.parse(map['yorum_id'].toString()),
      ustCevapId: map['ust_cevap_id'] != null && map['ust_cevap_id'].toString().isNotEmpty
          ? (map['ust_cevap_id'] is int ? map['ust_cevap_id'] : int.tryParse(map['ust_cevap_id'].toString()))
          : null,
      icerik: map['icerik']?.toString() ?? '',
      olusturulmaTarihi: map['olusturulma_tarihi'] is DateTime 
          ? map['olusturulma_tarihi'] 
          : DateTime.tryParse(map['olusturulma_tarihi']?.toString() ?? '') ?? DateTime.now(),
      yazarAdi: map['yazar_adi']?.toString() ?? map['username']?.toString(),
      yazarResmi: map['yazar_resmi']?.toString() ?? map['avatar']?.toString(),
    );
  }
}

class CommentModel {
  final int id;
  final int kullaniciId;
  final int tarifId;
  final String icerik;
  final DateTime olusturulmaTarihi;
  final String? yazarAdi;
  final String? yazarResmi;
  final List<CommentReplyModel> cevaplar;

  CommentModel({
    required this.id,
    required this.kullaniciId,
    required this.tarifId,
    required this.icerik,
    required this.olusturulmaTarihi,
    this.yazarAdi,
    this.yazarResmi,
    this.cevaplar = const [],
  });

  factory CommentModel.fromMap(Map<String, dynamic> map) {
    var repliesList = <CommentReplyModel>[];
    if (map['cevaplar'] != null && map['cevaplar'] is List) {
      final List list = map['cevaplar'];
      repliesList = list.map((item) => CommentReplyModel.fromMap(item)).toList();
    }

    return CommentModel(
      id: map['id'] is int ? map['id'] : int.parse(map['id'].toString()),
      kullaniciId: map['kullanici_id'] is int ? map['kullanici_id'] : int.parse(map['kullanici_id'].toString()),
      tarifId: map['tarif_id'] is int ? map['tarif_id'] : int.parse(map['tarif_id'].toString()),
      icerik: map['icerik']?.toString() ?? '',
      olusturulmaTarihi: map['olusturulma_tarihi'] is DateTime 
          ? map['olusturulma_tarihi'] 
          : DateTime.tryParse(map['olusturulma_tarihi']?.toString() ?? '') ?? DateTime.now(),
      yazarAdi: map['yazar_adi']?.toString() ?? map['username']?.toString(),
      yazarResmi: map['yazar_resmi']?.toString() ?? map['avatar']?.toString(),
      cevaplar: repliesList,
    );
  }
}
