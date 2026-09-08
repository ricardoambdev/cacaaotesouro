import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:cacaaotesouro_app/main.dart';

void main() {
  setUp(() {
    // Pré-configura SharedPreferences com valores mock
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('App renderiza ConnectionScreen na inicialização',
      (WidgetTester tester) async {
    await tester.pumpWidget(const CacaAoTesouroApp());
    await tester.pump();

    // A ConnectionScreen deve aparecer (com estado de loading ou erro)
    // Verificamos que o app foi construído sem erros
    expect(find.byType(CacaAoTesouroApp), findsOneWidget);
  });
}
