<?php
/*
  Descrição do Desafio:
    Você precisa realizar uma migração dos dados fictícios que estão na pasta <dados_sistema_legado> para a base da clínica fictícia MedicalChallenge.
    Para isso, você precisa:
      1. Instalar o MariaDB na sua máquina. Dica: Você pode utilizar Docker para isso;
      2. Restaurar o banco da clínica fictícia Medical Challenge: arquivo <medical_challenge_schema>;
      3. Migrar os dados do sistema legado fictício que estão na pasta <dados_sistema_legado>:
        a) Dica: você pode criar uma função para importar os arquivos do formato CSV para uma tabela em um banco temporário no seu MariaDB.
      4. Gerar um dump dos dados já migrados para o banco da clínica fictícia Medical Challenge.
*/

// Importação de Bibliotecas:
include "./lib.php";

// Conexão com o banco da clínica fictícia:
$connMedical = mysqli_connect("localhost", "root", "masterkey", "MedicalChallenge")
  or die("Não foi possível conectar os servidor MySQL: MedicalChallenge\n");

// Conexão com o banco temporário:
$connTemp = mysqli_connect("localhost", "root", "masterkey", "0temp")
  or die("Não foi possível conectar os servidor MySQL: 0temp\n");

// Informações de Inicio da Migração:
echo "Início da Migração: " . dateNow() . ".\n\n";


// Criando a tabela temporária:
$temp_paciente ="
  cod_paciente INT,
  nome_paciente VARCHAR(255),
  nasc_paciente DATE,
  pai_paciente VARCHAR(255),
  mae_paciente VARCHAR(255),
  cpf_paciente VARCHAR(20),
  rg_paciente VARCHAR(20),
  sexo_pac ENUM('Masculino', 'Feminino') NOT NULL,
  id_conv INT,
  convenio VARCHAR(255),
  obs_clinicas TEXT
";
criarTabelaTemporaria($connTemp, "pacientes", $temp_paciente);

$temp_agendamentos="
  cod_agendamento INT,
  descricao TEXT,
  dia DATE,
  hora_inicio TIME,
  hora_fim TIME,
  cod_paciente INT,
  paciente VARCHAR(255),
  cod_medico INT,
  medico VARCHAR(255),
  cod_convenio INT,
  convenio VARCHAR(255),
  procedimento VARCHAR(255)
";
criarTabelaTemporaria($connTemp, "agendamentos", $temp_agendamentos);

// Importando os dados para a tabela temporária:
importarCSV($connTemp, "pacientes", "./dados_sistema_legado/20210512_pacientes.csv");
importarCSV($connTemp, "agendamentos", "./dados_sistema_legado/20210512_agendamentos.csv");

// Migrando os dados para a base da clínica fictícia Medical Challenge:
migrarDados(connTemp: $connTemp, connMedical: $connMedical, nomeTabelaTemp: "pacientes", nomeTabelaMigra: "convenios", campoChecagemTemp: "convenio", campoChecagemMigra: "nome", colunasTemp: "convenio", colunasMigra: "nome");

// Encerrando as conexões:
$connMedical->close();
$connTemp->close();

// Informações de Fim da Migração:
echo "Fim da Migração: " . dateNow() . ".\n";

