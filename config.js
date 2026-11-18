/*
  Configuração do site "Isto não é um Vinho".
  É o ficheiro a mexer no dia a dia.
*/
window.SITE = {
  // Preço por garrafa em euros (ex.: 99 ou 24.5). Com null aparece "Preço em breve".
  preco: 99,

  // Garrafas que restam. Com 0, o formulário desaparece e aparece "Acabou mesmo."
  restam: 699,

  // Total da edição.
  total: 700,

  // Para onde o formulário envia (script PHP que manda os emails por SMTP).
  formEndpoint: "api/encomenda.php",

  // Email de recurso mostrado se o envio falhar.
  emailEncomendas: "geral@istonaoeumvinho.pt",

  instagram: "https://www.instagram.com/istonaoeumvinho/"
};
