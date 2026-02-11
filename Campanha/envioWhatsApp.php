<?php
/**
 * WhatsApp Cloud API - PHP 5.4
 * Envia imagem + legenda (texto) para um número.
 *
 * Requisitos:
 * - Conta Meta / WhatsApp Business Platform
 * - PHONE_NUMBER_ID
 * - ACCESS_TOKEN
 * - Banner em arquivo (JPG/PNG)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// =====================
// CONFIGURAÇÕES
// =====================
$ACCESS_TOKEN   = 'SEU_ACCESS_TOKEN_AQUI';
$PHONE_NUMBER_ID = 'SEU_PHONE_NUMBER_ID_AQUI'; // ex: 123456789012345
$GRAPH_VERSION  = 'v20.0'; // use a versão suportada na sua conta
$BASE_URL       = "https://graph.facebook.com/{$GRAPH_VERSION}/{$PHONE_NUMBER_ID}";

// Banner local (arquivo gerado por você)
$BANNER_PATH = __DIR__ . '/banner.png'; // ajuste para seu arquivo (png/jpg)

// Mensagem (vai como "caption" da imagem)
$messageText =
"Prezados moradores,\n\n".
"Compartilho esta mensagem com respeito e senso de responsabilidade.\n\n".
"O Village é de todos.\n".
"Quando cuidamos juntos hoje, todos se beneficiam amanhã.\n\n".
"Coloco meu nome à disposição como candidato a síndico, acreditando em uma gestão baseada em cuidado, continuidade, diálogo e compromisso com o bem comum.\n\n".
"Fico à disposição para ouvir sugestões, opiniões e contribuições.\n\n".
"Atenciosamente,\n".
"Rogério Caixeta\n".
"Candidato a Síndico\n".
"Gestão 2026/2028";

// Lista de destinatários (formato internacional E.164, sem "+" e sem espaços)
// Ex.: Brasil: 55 + DDD + número
$recipients = array(
  '5561999999999',
  // '5561988888888',
);

// =====================
// FUNÇÕES AUXILIARES
// =====================
function curl_json_post($url, $accessToken, $payloadArr) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer {$accessToken}",
    "Content-Type: application/json"
  ));
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadArr));
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new Exception("cURL error: {$err}");
  }
  curl_close($ch);

  $data = json_decode($resp, true);
  if ($http < 200 || $http >= 300) {
    $msg = isset($data['error']['message']) ? $data['error']['message'] : $resp;
    throw new Exception("HTTP {$http} - {$msg}");
  }
  return $data;
}

function upload_media($baseUrl, $accessToken, $filePath) {
  if (!file_exists($filePath)) {
    throw new Exception("Arquivo não encontrado: {$filePath}");
  }

  $url = $baseUrl . '/media';

  // PHP 5.4: CURLFile pode não existir em algumas builds antigas.
  // Use @ para upload legado, se necessário.
  $postFields = array(
    'messaging_product' => 'whatsapp',
    'type' => 'image/png', // ou image/jpeg
    'file' => '@' . $filePath
  );

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer {$accessToken}"
  ));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($resp === false) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new Exception("cURL upload error: {$err}");
  }
  curl_close($ch);

  $data = json_decode($resp, true);
  if ($http < 200 || $http >= 300) {
    $msg = isset($data['error']['message']) ? $data['error']['message'] : $resp;
    throw new Exception("Upload HTTP {$http} - {$msg}");
  }

  if (!isset($data['id'])) {
    throw new Exception("Upload sem media id. Resposta: {$resp}");
  }

  return $data['id'];
}

function send_image_with_caption($baseUrl, $accessToken, $to, $mediaId, $caption) {
  $url = $baseUrl . '/messages';

  $payload = array(
    'messaging_product' => 'whatsapp',
    'to' => $to,
    'type' => 'image',
    'image' => array(
      'id' => $mediaId,
      'caption' => $caption
    )
  );

  return curl_json_post($url, $accessToken, $payload);
}

// (Opcional) enviar texto separado
function send_text($baseUrl, $accessToken, $to, $text) {
  $url = $baseUrl . '/messages';

  $payload = array(
    'messaging_product' => 'whatsapp',
    'to' => $to,
    'type' => 'text',
    'text' => array(
      'preview_url' => false,
      'body' => $text
    )
  );

  return curl_json_post($url, $accessToken, $payload);
}

// =====================
// EXECUÇÃO
// =====================
try {
  // 1) Upload da imagem (uma vez só)
  $mediaId = upload_media($BASE_URL, $ACCESS_TOKEN, $BANNER_PATH);

  // 2) Envio para cada destinatário
  foreach ($recipients as $to) {
    $result = send_image_with_caption($BASE_URL, $ACCESS_TOKEN, $to, $mediaId, $messageText);

    // Log simples
    echo "Enviado para {$to}. Message ID: " . (isset($result['messages'][0]['id']) ? $result['messages'][0]['id'] : 'N/A') . "\n";

    // Pequena pausa para evitar rate-limit
    usleep(300000); // 0,3s
  }

  echo "Concluído.\n";
} catch (Exception $e) {
  echo "ERRO: " . $e->getMessage() . "\n";
}
