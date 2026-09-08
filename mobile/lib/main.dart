import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'screens/connection_screen.dart';
import 'theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  // Orientação retrato apenas
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  // Barra de status transparente
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    systemNavigationBarColor: AppColors.navyDark,
    systemNavigationBarIconBrightness: Brightness.light,
  ));
  runApp(const CacaAoTesouroApp());
}

class CacaAoTesouroApp extends StatelessWidget {
  const CacaAoTesouroApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Caça ao Tesouro',
      debugShowCheckedModeBanner: false,
      theme: appTheme(),
      home: const ConnectionScreen(),
    );
  }
}
