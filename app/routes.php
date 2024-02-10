<?php

// Home
$app->get('/home', 'HomeController:index');

// Text controller
$app->get('/getTexts', 'TkTextController:getTexts');
$app->get('/getMenu', 'TkTextController:getMenu');
$app->get('/getSingleText', 'TkTextController:getSingleText');
$app->get('/text/[{id}]', 'TkTextController:textId'); 

// TkSchoolController
$app->get('/teacher', 'TkSchoolController:teacher');
$app->get('/courseDef', 'TkSchoolController:courseDef');
$app->get('/scheduleSingleCourse', 'TkSchoolController:ScheduleSingleCourse');
$app->get('/scheduleCourse', 'TkSchoolController:ScheduleCourse');
$app->get('/scheduleSocial', 'TkSchoolController:ScheduleSocial');
$app->get('/getPartnerSearchList', 'TkSchoolController:getPartnerSearchList');
$app->get('/getWaitlist', 'TkSchoolController:getWaitlist');
$app->post('/addWaitlist', 'TkSchoolController:addWaitlist');
$app->post('/copySchedule', 'TkSchoolController:copySchedule');
$app->post('/copyScheduleDef', 'TkSchoolController:copyScheduleDef');
$app->get('/getCopyScheduleEvent', 'TkSchoolController:getCopyScheduleEvent');
$app->get('/getRegistrationCount', 'TkSchoolController:getRegistrationCount');
$app->get('/updatePhonebook', 'TkSchoolController:updatePhonebook');
$app->post('/cancelRegistration', 'TkSchoolController:cancelRegistration');
$app->get('/getNews', 'TkSchoolController:getNews');


// TkFestivalController (festivals and marathon)
$app->post('/copyWorkshopTemplate', 'TkFestivalController:copyWorkshopTemplate');
$app->post('/copyPackageTemplate', 'TkFestivalController:copyPackageTemplate');
$app->get('/getMarathonRange', 'TkFestivalController:getMarathonRange');
$app->get('/scheduleEvent', 'TkFestivalController:scheduleEvent');
$app->get('/packageDef', 'TkFestivalController:packageDef');
$app->get('/scheduleWorkshop', 'TkFestivalController:scheduleWorkshop');
$app->get('/partnerMissRegistration', 'TkFestivalController:partnerMissRegistration');
$app->get('/formFields', 'TkFestivalController:formFields');
$app->post('/createRegistrationMarathon', 'TkFestivalController:createRegistrationMarathon');
$app->post('/createRegistrationFestival', 'TkFestivalController:createRegistrationFestival');


// DiscountController
$app->get('/testDiscount', 'TkDiscountController:testDiscount');
$app->get('/testDiscountShoppingCart', 'TkDiscountController:testDiscountShoppingCart');
$app->post('/Discount', 'TkDiscountController:Discount');
$app->post('/DiscountShoppingCartList', 'TkDiscountController:DiscountShoppingCartList');
  
// TkShopController
$app->get('/shopImages', 'TkShopController:shopImages');
$app->get('/shopShowImages', 'TkShopController:shopShowImages');
$app->get('/sell', 'TkShopController:sell');
$app->post('/postSell', 'TkShopController:postSell');
$app->post('/createOrder', 'TkShopController:createOrder');
$app->post('/createRegistration', 'TkShopController:createRegistration');
$app->get('/testOrder', 'TkShopController:testOrder');

// TkInventoryController
$app->get('/getInventory', 'TkInventoryController:getInventory');
$app->get('/getProductDef', 'TkInventoryController:getProductDef');
$app->post('/updateProductDef', 'TkInventoryController:updateProductDef');
$app->get('/getProductInventory', 'TkInventoryController:getProductInventory');
$app->post('/updateProductInventory', 'TkInventoryController:updateProductInventory');
$app->post('/updateProduct', 'TkInventoryController:updateProduct');
$app->get('/getProducts', 'TkInventoryController:getProducts');
$app->get('/getImages', 'TkInventoryController:getImages');
$app->post('/renameImage', 'TkInventoryController:renameImage');
$app->post('/renameImages', 'TkInventoryController:renameImages');



// TkMailController
$app->get('/testMail', 'TkMailController:testMail');
$app->get('/testMailText', 'TkMailController:testMailText');
$app->get('/testMailSubject', 'TkMailController:testMailSubject');
$app->get('/sendMail', 'TkMailController:sendMail');
$app->get('/sendMailTest', 'TkMailController:sendMailTest');


// MailController
$app->get('/vtestMail', 'MailController:testMail');
$app->get('/vtestMailText', 'MailController:testMailText');
$app->get('/vtestMailSubject', 'MailController:testMailSubject');

// TestMailController
$app->get('/testEchoMail', 'TestMailController:testEchoMail');

// TkPaypalController
$app->get('/payment', 'TkPaypalController:payment');
$app->post('/payment', 'TkPaypalController:payment');

// TkBamboraController
$app->get('/getPaymentRequestBambora', 'TkBamboraController:getPaymentRequest');
$app->post('/paymentRequestBambora', 'TkBamboraController:paymentRequest');
$app->get('/paymentCallbackBambora', 'TkBamboraController:paymentCallback');


// TkSwishController
$app->get('/testPaymentRequestSwish', 'TkSwishController:testPaymentRequest');
$app->post('/paymentRequestSwish', 'TkSwishController:paymentRequest');
$app->get('/testPaymentCallbackSwish', 'TkSwishController:testPaymentCallback');
$app->post('/paymentCallbackSwish', 'TkSwishController:paymentCallback');

// TkImageController
$app->get('/getImage', 'TkImageController:getImage');
$app->get('/listDirs', 'TkImageController:listDirs');
$app->get('/listImages', 'TkImageController:listImages');
$app->get('/listImagesData', 'TkImageController:listImagesData');
$app->get('/listThumbnails', 'TkImageController:listThumbnails');
$app->get('/createThumbnails', 'TkImageController:createThumbnails');
$app->get('/getRemoveFile', 'TkImageController:getRemoveFile');
$app->post('/postImage', 'TkImageController:postImage');
$app->post('/postImages', 'TkImageController:postImages');
$app->post('/removeOrRotateImages', 'TkImageController:removeOrRotateImages');
$app->post('/testFiles', 'TkImageController:testFiles');
$app->post('/createDirectory', 'TkImageController:createDirectory');
$app->post('/deleteDirectory', 'TkImageController:deleteDirectory');

// ImageController
$app->get('/getImageList', 'ImageController:getImageList');
$app->post('/setImageList', 'ImageController:setImageList');
$app->get('/getJsonFromFile', 'ImageController:getJsonFromFile');
$app->post('/setJsonInFile', 'ImageController:setJsonInFile');

// admin 
$app->get('/admin/getRegistration', 'TkSchoolController:getRegistration');
$app->get('/admin/getRegistrationHistory', 'TkSchoolController:getRegistrationHistory');
$app->get('/admin/tktable', 'TkTableController:index');
$app->get('/admin/tktableWithoutId', 'TkTableController:withoutId');
$app->get('/admin/readFile', 'TkTableController:readFile');
$app->get('/admin/tkcolumns', 'TkColumnsController:index');
$app->get('/admin/getOrder', 'TkShopController:getOrder');
$app->post('/admin/crud', 'TkTableController:crud');
$app->post('/admin/deleteRow', 'TkTableController:deleteRow');
$app->post('/admin/replaceRow', 'TkTableController:replaceRow');
$app->post('/admin/updateRow', 'TkTableController:updateRow');
$app->post('/admin/updateTableAll', 'TkSchoolController:updateTableAll');
$app->get('/admin/getTable', 'TkTableController:getTable');
$app->get('/getUpdateTable', 'TkSchoolController:getUpdateTable');
$app->post('/admin/insertTable', 'TkSchoolController:insertTable');
$app->get('/getInsertTable', 'TkSchoolController:getInsertTable');
$app->post('/admin/updateRowsInPresence', 'TkSchoolController:updateRowsInPresence');
$app->post('/admin/updateProductId', 'TkSchoolController:updateProductId');
$app->post('/admin/scheduleChange', 'TkSchoolController:scheduleChange');
$app->get('/admin/getRegistrationMarathon', 'TkFestivalController:getRegistrationMarathon');
$app->get('/admin/getCountersMarathon', 'TkFestivalController:getCountersMarathon');
$app->get('/admin/getCountersFestival', 'TkFestivalController:getCountersFestival');
$app->get('/admin/getMarathonNames', 'TkFestivalController:getMarathonNames');
$app->get('/admin/getRegistrationFestival', 'TkFestivalController:getRegistrationFestival');
$app->get('/admin/getRegistrationFestivalByProduct', 'TkFestivalController:getRegistrationFestivalByProduct');
$app->get('/admin/getRegistrationFestivalByTotal', 'TkFestivalController:getRegistrationFestivalByTotal');
$app->get('/admin/getColumns', 'TkTableController:getColumns');

$app->get('/getPresence', 'TkSchoolController:getPresence');
$app->get('/getPresenceHistory', 'TkSchoolController:getPresenceHistory');
$app->get('/getPresenceHistoryMatrix', 'TkSchoolController:getPresenceHistoryMatrix');
$app->get('/getTeacherNote', 'TkSchoolController:getTeacherNote');

// CalendarController
$app->get('/getEvents', 'CalendarController:getEvents');
$app->post('/addEvent', 'CalendarController:addEvent');
$app->post('/addEvents', 'CalendarController:addEvents');
$app->post('/cancelEvent', 'CalendarController:cancelEvent');
$app->post('/updateEvent', 'CalendarController:updateEvent');

//$app->get('/tkcolumns_test', 'TkColumnsController:test');
//$app->get('/tktable_test', 'TkTableController:test');
//$app->put('/admin/tktable_update/[{id}]', 'TkTableController:update');
//$app->delete('/admin/tktable_delete_test/[{id}]', 'TkTableController:deleteTest');
//$app->delete('/admin/tktable_delete/[{id}]', 'TkTableController:delete');
//$app->post('/admin/tktable_insert', 'TkTableController:insert');
//$app->put('/admin/tktable_update_test/[{id}]', 'TkTableController:updateTest');
// $app->post('/admin/tktable_insert_test', 'TkTableController:insertTest');
