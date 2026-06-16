// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

class RecipeModel {
  final int id;
  final int kullaniciId;
  final String baslik;
  final String? aciklama;
  final String malzemeler;
  final String hazirlanisi;
  final int? hazirlamaSuresi;
  final int? pisirmeSuresi;
  final int kisiSayisi;
  final String? resimUrl;
  final DateTime olusturulmaTarihi;

  // Entegrasyon ve Akış için ek alanlar (sorgulardan gelen dinamik alanlar)
  final String? yazarAdi;
  final String? yazarResmi;
  final int begeniSayisi;
  final int yorumSayisi;
  final double ortalamaPuan;
  final int puanSayisi;
  final bool begendiMi;

  RecipeModel({
    required this.id,
    required this.kullaniciId,
    required this.baslik,
    this.aciklama,
    required this.malzemeler,
    required this.hazirlanisi,
    this.hazirlamaSuresi,
    this.pisirmeSuresi,
    this.kisiSayisi = 1,
    this.resimUrl,
    required this.olusturulmaTarihi,
    this.yazarAdi,
    this.yazarResmi,
    this.begeniSayisi = 0,
    this.yorumSayisi = 0,
    this.ortalamaPuan = 0.0,
    this.puanSayisi = 0,
    this.begendiMi = false,
  });

  factory RecipeModel.fromMap(Map<String, dynamic> map) {
    return RecipeModel(
      id: map['id'] is int ? map['id'] : int.parse(map['id'].toString()),
      kullaniciId: map['kullanici_id'] is int ? map['kullanici_id'] : int.parse(map['kullanici_id'].toString()),
      baslik: map['baslik']?.toString() ?? '',
      aciklama: map['aciklama']?.toString(),
      malzemeler: map['malzemeler']?.toString() ?? '',
      hazirlanisi: map['hazirlanisi']?.toString() ?? '',
      hazirlamaSuresi: map['hazirlama_suresi'] != null 
          ? (map['hazirlama_suresi'] is int ? map['hazirlama_suresi'] : int.tryParse(map['hazirlama_suresi'].toString()))
          : null,
      pisirmeSuresi: map['pisirme_suresi'] != null 
          ? (map['pisirme_suresi'] is int ? map['pisirme_suresi'] : int.tryParse(map['pisirme_suresi'].toString()))
          : null,
      kisiSayisi: map['kisi_sayisi'] != null 
          ? (map['kisi_sayisi'] is int ? map['kisi_sayisi'] as int : int.tryParse(map['kisi_sayisi'].toString()) ?? 1)
          : 1,
      resimUrl: map['resim_url']?.toString(),
      olusturulmaTarihi: map['olusturulma_tarihi'] is DateTime 
          ? map['olusturulma_tarihi'] 
          : DateTime.tryParse(map['olusturulma_tarihi']?.toString() ?? '') ?? DateTime.now(),
      yazarAdi: map['yazar_adi']?.toString(),
      yazarResmi: map['yazar_resmi']?.toString(),
      begeniSayisi: map['begeni_sayisi'] != null 
          ? (map['begeni_sayisi'] is int ? map['begeni_sayisi'] as int : int.tryParse(map['begeni_sayisi'].toString()) ?? 0)
          : 0,
      yorumSayisi: map['yorum_sayisi'] != null 
          ? (map['yorum_sayisi'] is int ? map['yorum_sayisi'] as int : int.tryParse(map['yorum_sayisi'].toString()) ?? 0)
          : 0,
      ortalamaPuan: map['ortalama_puan'] != null 
          ? (map['ortalama_puan'] is double ? map['ortalama_puan'] as double : double.tryParse(map['ortalama_puan'].toString()) ?? 0.0)
          : 0.0,
      puanSayisi: map['puan_sayisi'] != null 
          ? (map['puan_sayisi'] is int ? map['puan_sayisi'] as int : int.tryParse(map['puan_sayisi'].toString()) ?? 0)
          : 0,
      begendiMi: map['begendi_mi'] == true || map['begendi_mi'] == 1 || map['begendi_mi']?.toString() == 'true',
    );
  }

  RecipeModel copyWith({
    bool? begendiMi,
    int? begeniSayisi,
    double? ortalamaPuan,
    int? puanSayisi,
  }) {
    return RecipeModel(
      id: id,
      kullaniciId: kullaniciId,
      baslik: baslik,
      aciklama: aciklama,
      malzemeler: malzemeler,
      hazirlanisi: hazirlanisi,
      hazirlamaSuresi: hazirlamaSuresi,
      pisirmeSuresi: pisirmeSuresi,
      kisiSayisi: kisiSayisi,
      resimUrl: resimUrl,
      olusturulmaTarihi: olusturulmaTarihi,
      yazarAdi: yazarAdi,
      yazarResmi: yazarResmi,
      begeniSayisi: begeniSayisi ?? this.begeniSayisi,
      yorumSayisi: yorumSayisi,
      ortalamaPuan: ortalamaPuan ?? this.ortalamaPuan,
      puanSayisi: puanSayisi ?? this.puanSayisi,
      begendiMi: begendiMi ?? this.begendiMi,
    );
  }
}
