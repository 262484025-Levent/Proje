// **
// Adı Soyadı: Emirhan Özerdil
// Öğrenci Numarası: 262484016
// **

class UserModel {
  final int id;
  final String kullaniciAdi;
  final String eposta;
  final String? profilResmi;
  final String? biyografi;
  final DateTime olusturulmaTarihi;

  UserModel({
    required this.id,
    required this.kullaniciAdi,
    required this.eposta,
    this.profilResmi,
    this.biyografi,
    required this.olusturulmaTarihi,
  });

  factory UserModel.fromMap(Map<String, dynamic> map) {
    return UserModel(
      id: map['id'] is int ? map['id'] : int.parse(map['id'].toString()),
      kullaniciAdi: map['kullanici_adi']?.toString() ?? '',
      eposta: map['eposta']?.toString() ?? '',
      profilResmi: map['profil_resmi']?.toString(),
      biyografi: map['biyografi']?.toString(),
      olusturulmaTarihi: map['olusturulma_tarihi'] is DateTime 
          ? map['olusturulma_tarihi'] 
          : DateTime.tryParse(map['olusturulma_tarihi']?.toString() ?? '') ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toMap() {
    return {
      'id': id,
      'kullanici_adi': kullaniciAdi,
      'eposta': eposta,
      'profil_resmi': profilResmi,
      'biyografi': biyografi,
      'olusturulma_tarihi': olusturulmaTarihi.toIso8601String(),
    };
  }
}
