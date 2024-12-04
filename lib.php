<?php
/*
  Biblioteca de Funções.
    Você pode separar funções muito utilizadas nesta biblioteca, evitando replicação de código.
*/

function criarTabelaTemporaria($conn, $nomeTabela, $colunas){
  $sql = "CREATE TABLE $nomeTabela ($colunas)";

  if ($conn->query($sql) === TRUE) {
    echo "Tabela  temporária criada.\n";
  } else {
      echo "Erro ao criar tabela  temporária: " . $conn->error . "\n";
  }
}

// Função para formatar a data corretamente
function formatarData($data) {
  // Verifica se a data é válida no formato dd/mm/aaaa
  $dataFormatada = DateTime::createFromFormat('d/m/Y', $data);
  if ($dataFormatada && $dataFormatada->format('d/m/Y') === $data) {
      return $dataFormatada->format('Y-m-d'); // Formato MySQL
  }
  return "NULL"; // Retorna NULL se a data for inválida
}

function formatarSexo($sexo) {
  return $sexo === 'M' ? 'Masculino' : 'Feminino';
}

function importarCSV($conn, $nomeTabela, $arquivo) {
  $handle = fopen($arquivo, 'r');
  if (!$handle) {
      die("Erro ao abrir o arquivo: $arquivo");
  }

  // Ignora a primeira linha (cabeçalho)
  fgetcsv($handle, 1000, ";");

  while (($dados = fgetcsv($handle, 1000, ";")) !== false) {
      // Se a data de nascimento (índice 2) for inválida, substitui por NULL
      $dados[2] = formatarData($dados[2]);
      if ($dados[7] === 'M' || $dados[7] === 'F')  
        $dados[7] = formatarSexo($dados[7]); 
      
      // Preenche campos vazios com NULL
      $campos = implode(", ", array_map(fn($coluna) => $coluna === '' ? "NULL" : "'$coluna'", $dados)); 

      // Monta o comando SQL de inserção
      $sql = "INSERT INTO $nomeTabela VALUES ($campos)";

      // Executa o comando SQL
      if (!$conn->query($sql)) {
          echo "Erro ao inserir na tabela $nomeTabela: " . $conn->error . "\n";
      }
  }
  fclose($handle);
}


function migrarDados($connTemp, $connMedical, $nomeTabelaTemp, $nomeTabelaMigra, $campoChecagemTemp, $campoChecagemMigra, $colunasTemp, $colunasMigra): void {
  // Seleciona todos os dados da tabela temporária
  $sqlSelect = "SELECT $colunasTemp FROM $nomeTabelaTemp";
  $result = $connTemp->query($sqlSelect);

  if ($result === false) {
      die("Erro ao selecionar dados da tabela temporária: " . $connTemp->error);
  }

  if ($result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
          // Verifica se o registro já existe na tabela de destino
          $campoChecagemValor = $row[$campoChecagemTemp]; // Valor do campo para verificação

          // Preparando a consulta de verificação
          $sqlCheck = "SELECT 1 FROM $nomeTabelaMigra WHERE $campoChecagemMigra = ?";
          $stmtCheck = $connMedical->prepare($sqlCheck);
          $stmtCheck->bind_param('s', $campoChecagemValor);
          $stmtCheck->execute();
          $stmtCheck->store_result();

          if ($stmtCheck->num_rows === 0) {
              // Monta a instrução de inserção
              // Escapando e tratando valores corretamente
              $valores = array_map(function($value) use ($connMedical) {
                  return $value === null ? "NULL" : "'" . $connMedical->real_escape_string($value) . "'";
              }, array_values($row)); // Escapa os valores e trata valores NULL

              $sqlInsert = "INSERT INTO $nomeTabelaMigra ($colunasMigra) VALUES (" . implode(", ", $valores) . ")";

              if ($connMedical->query($sqlInsert) === TRUE) {
                  echo "Registro inserido com sucesso na tabela $nomeTabelaMigra.\n";
              } else {
                  echo "Erro ao inserir registro na tabela $nomeTabelaMigra: " . $connMedical->error . "\n";
              }
          } else {
              echo "Registro com $campoChecagemMigra = $campoChecagemValor já existe na tabela $nomeTabelaMigra.\n";
          }

          // Fechar o statement de verificação
          $stmtCheck->close();
      }
  }
}

function dateNow(){
  date_default_timezone_set('America/Sao_Paulo');
  return date('d-m-Y \à\s H:i:s');
}