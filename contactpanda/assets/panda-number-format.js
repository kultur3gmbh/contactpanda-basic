(function(){'use strict';
 window.PandaNumberFormat=function(value,options){
  var settings=Object.assign({},window.PANDA_NUMBER_FORMAT||{},options||{}),locale=(settings.locale||document.documentElement.lang||navigator.language||'en').replace(/_(formal|informal)$/,'').replace(/_/g,'-'),number=Number(value);
  if(!Number.isFinite(number))return '';
  var digits=settings.decimals===undefined?(settings.currency?2:Math.min(6,String(value).split('.')[1]?.length||0)):Number(settings.decimals),format={minimumFractionDigits:digits,maximumFractionDigits:digits};
  if(settings.currency){format.style='currency';format.currency=settings.currency;}
  var formatter;try{formatter=new Intl.NumberFormat(locale,format);}catch(ignore){formatter=new Intl.NumberFormat(document.documentElement.lang||'en',format);}
  return formatter.formatToParts(number).map(function(part){if(part.type==='group'&&settings.thousands!==undefined)return settings.thousands;if(part.type==='decimal'&&settings.decimal!==undefined)return settings.decimal;return part.value;}).join('');
 };
})();
