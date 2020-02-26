<?php
/******************************************************************************/
//                                                                            //
//                           InstantCMS v1.10.3                               //
//                        http://www.instantcms.ru/                           //
//                                                                            //
//                   written by InstantCMS Team, 2007-2013                    //
//                produced by InstantSoft, (www.instantsoft.ru)               //
//                                                                            //
//                        LICENSED BY GNU/GPL v2                              //
//                                                                            //
/******************************************************************************/

if(!defined('VALID_CMS_ADMIN')) { die('ACCESS DENIED'); }

function applet_tree(){

    $inCore = cmsCore::getInstance();
    $inUser = cmsUser::getInstance();
    $inDB 	= cmsDatabase::getInstance();
    $inPage = cmsPage::getInstance();

    cmsCore::loadLib('tags');

    global $_LANG;
    global $adminAccess;
    if (!cmsUser::isAdminCan('admin/content', $adminAccess)) { cpAccessDenied(); }

    $cfg = $inCore->loadComponentConfig('content');

    cmsCore::loadModel('content');
    $model = new cms_model_content();

    $GLOBALS['cp_page_title'] = $_LANG['AD_ARTICLES'];
    cpAddPathway($_LANG['AD_ARTICLES'], 'index.php?view=tree');

    $GLOBALS['cp_page_head'][] = '<script language="JavaScript" type="text/javascript" src="js/content.js"></script>';
    echo '<script>';
    echo cmsPage::getLangJS('AD_NO_SELECTED_ARTICLES');
    echo cmsPage::getLangJS('AD_DELETE_SELECTED_ARTICLES');
    echo cmsPage::getLangJS('AD_PIECES');
    echo cmsPage::getLangJS('AD_CATEGORY_DELETE');
    echo cmsPage::getLangJS('AD_AND_SUB_CATS');
    echo cmsPage::getLangJS('AD_DELETE_SUB_ARTICLES');
    echo '</script>';

    $do = cmsCore::request('do', 'str', 'tree');
    $target = cmsCore::request('target', 'str');

//============================================================================//
//============================================================================//
    
    if ($do == 'tree' && !$target){

        $toolmenu[] = array('icon'=>'config.gif', 'title'=>$_LANG['AD_SETUP_CATEGORY'], 'link'=>'?view=components&do=config&link=content');
        $toolmenu[] = array('icon'=>'newmarker.gif', 'title'=>'Поиск координат', 'link'=>'?view=tree&target=geocode');
        $toolmenu[] = array('icon'=>'listdiscount.gif', 'title'=>'Генератор координат', 'link'=>'?view=tree&target=geogenerator');
        $toolmenu[] = array('icon'=>'newmarker.gif', 'title'=>'Поиск координат панорам', 'link'=>'?view=tree&target=pangenerator');
        $toolmenu[] = array('icon'=>'help.gif', 'title'=>$_LANG['AD_HELP'], 'link'=>'?view=components&do=config&link=content');

        cpToolMenu($toolmenu);

        $only_hidden    = cmsCore::request('only_hidden', 'int', 0);
        $category_id    = cmsCore::request('cat_id', 'int', 0);
        $base_uri       = 'index.php?view=tree';

        $title_part     = cmsCore::request('title', 'str', '');

        $def_order  = $category_id ? 'con.ordering' : 'pubdate';
        $orderby    = cmsCore::request('orderby', 'str', $def_order);
        $orderto    = cmsCore::request('orderto', 'str', 'asc');
        $page       = cmsCore::request('page', 'int', 1);
        $perpage    = 20;

        $hide_cats  = cmsCore::request('hide_cats', 'int', 0);

        $cats       = $model->getCatsTree();

//создание статей в категориях одноименных с категорией
//foreach($cats as $cat){
//    if($cat['parent_id']==1) { continue; }
//    
//    $article = array();
//    $article['title']       = $cat['title'];
//    $article['category_id'] = $cat['id'];
//    $article['url']         = 'panorama-'.$article['title'];
//    $article['showtitle']   = 1;
//    
//    $article['published']   = 1;
//    $article['showdate']    = 0;
//    
//    $article['showlatest']  = 1;
//    $article['showpath']    = 0;
//    $article['comments']    = 0;
//    $article['canrate']     = 0;
//        
//    $article['user_id'] = 1;
//    $article['tpl'] 	= 'com_content_read.tpl';
//            
//    $article['meta_desc'] = $article['title'];
//    $article['country']   = 'Россия';
//    $article['city']      = $cat['title'];
//    
//    $model->addArticle($article);
//}

        if ($category_id) {
            $model->whereCatIs($category_id);
        }

        if ($title_part){
            $inDB->where('LOWER(con.title) LIKE \'%'.mb_strtolower($title_part).'%\'');
        }

        if ($only_hidden){
            $inDB->where('con.published = 0');
        }

        $inDB->orderBy($orderby, $orderto);

        $inDB->limitPage($page, $perpage);

        $total = $model->getArticlesCount(false);

        $items = $model->getArticlesList(false);

        $pages = ceil($total / $perpage);


        $tpl_file   = 'admin/content.php';
        $tpl_dir    = file_exists(TEMPLATE_DIR.$tpl_file) ? TEMPLATE_DIR : DEFAULT_TEMPLATE_DIR;

        include($tpl_dir.$tpl_file);

    }
        
//определение координат для городов
    if($target == 'geocode'){
        
        if ($inCore->inRequest('save')){
            $items = $inCore->request('item', 'array');
            if (!is_array($items)) { $inCore->redirectBack(); }
            foreach($items as $item_id=>$pos){
                $item_id = intval($item_id);
                $inDB->query("UPDATE cms_category SET lat='{$pos['lat']}', lng='{$pos['lng']}' WHERE id = '{$item_id}'");
            }
            $inCore->redirect('index.php?view=tree');
        }

        cpAddPathway('Поиск координат', $_SERVER['REQUEST_URI']);
        echo '<h3>Поиск координат</h3>';
        
        $countries = array();
        $sql = "SELECT id, title FROM cms_category WHERE role = 'country' AND parent_id = 1";
        $res = $inDB->query($sql);
        if ($inDB->num_rows($res) ) {
            while ($country = $inDB->fetch_assoc($res)) {
                $countries[$country['id']] = $country['title'];
            }
        }
        
        $sql = "SELECT m.id as id,
               m.lat as addr_lat,
               m.lng as addr_lng,
               m.title, m.title as addr_city, m.parent_id, 
               p.title as region 
            FROM cms_category m 
            LEFT JOIN cms_category p ON p.id = m.parent_id 
            WHERE m.lat = '' AND m.lng = '' AND m.parent_id = 1 LIMIT 200";

        $result = $inDB->query($sql);

        $items = array();

        $count = $inDB->num_rows($result);

        if ($count) {

            while ($item = $inDB->fetch_assoc($result)){
                $country = 'Россия';
                $address = $item['addr_city'];
                $address = $country . ', ' . $address;
                $item['map_address'] = $address;
                $items[] = $item;
            }

        } ?>

    <?php if (!$count) { ?>

        <p>В каталоге нет объектов без координат. Поиск не требуется.</p>

    <?php } else { ?>
        
        <?php if ($cfg['maps_engine'] == '2gis'){ $cfg['maps_engine'] = 'google'; } ?>
        <script src='/components/content/systems/<?php echo $cfg['maps_engine']; ?>/geo.js' type='text/javascript'></script>
        
        <?php

            if (in_array($cfg['maps_engine'], array('yandex', 'narod', 'custom'))){
                $key = $cfg['yandex_key'];
            } else {
                $key = $cfg[$cfg['maps_engine'].'_key'];
            }

            $inCore->includeFile('components/content/systems/'.$cfg['maps_engine'].'/info.php');
            $api_key = str_replace('#key#', $key, $GLOBALS['MAP_API_URL']);

        ?>

        <?php echo $api_key; ?>
        
        <form action="" method="post">

            <table cellpadding="4" cellspacing="0" border="0" class="proptable" style="border:none">
                <tr>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Объект</strong>
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Полный адрес</strong>
                    </td>
                    <td width="">
                        <strong>Долгота, широта</strong>
                    </td>
                </tr>
                <?php foreach($items as $item){ ?>
                <tr class="item_row" rel="<?php echo $item['id']; ?>">
                    <td style="padding:0px;padding-right:20px;">
                        <strong style="color:#000"><?php echo $item['title']; ?></strong>
                    </td>
                    <td class="addr" style="padding:0px;padding-right:20px;"><?php echo $item['map_address']; ?></td>
                    <td width="">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lng]" id="<?php echo $item['id']; ?>_lng" value="" disabled="disabled" />
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lat]" id="<?php echo $item['id']; ?>_lat" value="" disabled="disabled" />
                    </td>
                </tr>
                <?php } ?>
            </table>

            <p class="start_detect">
                <span style="color: #09C">
                    <strong>Найдено объектов без координат:</strong>
                </span> <?php echo $count; ?> шт.
                <input style="margin-left:20px" type="button" value="Начать поиск координат..." onclick="detectLatLngList();$(this).val('Подождите...').prop('disabled', 'disabled')" />
            </p>

            <p class="save_detect" style="display:none">
                <span style="color: #09C">
                    <strong>Поиск завершен</strong>
                </span>
                <input style="margin-left:20px" type="submit" name="save" value="Сохранить координаты" />
            </p>

        </form>
    <?php } 

    }
    
//генератор координат
    if($target == 'geogenerator'){
        
        if ($inCore->inRequest('save')){
            $items = $inCore->request('item', 'array');
            if (!is_array($items)) { $inCore->redirectBack(); }
            foreach($items as $item_id=>$pos){
                $item_id = intval($item_id);
                $inDB->query("UPDATE cms_content SET lat='{$pos['lat']}', lng='{$pos['lng']}', city = '{$pos['city']}', country = '{$pos['country']}' WHERE id = '{$item_id}'");
            }
            $inCore->redirect('index.php?view=tree');
        }

        cpAddPathway('Генератор координат', $_SERVER['REQUEST_URI']);
        echo '<h3>Генератор координат</h3>';
        
        $countries = array();
        $sql = "SELECT id, title FROM cms_category WHERE role = 'country' AND parent_id = 1";
        $res = $inDB->query($sql);
        if ($inDB->num_rows($res) ) {
            while ($country = $inDB->fetch_assoc($res)) {
                $countries[$country['id']] = $country['title'];
            }
        }
        
        $sql = "SELECT con.id, con.category_id, 
               con.lat as addr_lat, 
               con.lng as addr_lng, 
               con.title, 
               cat.id as cat_id, cat.title as city_title, cat.lat as city_lat, cat.lng as city_lng, cat.parent_id as cat_parent_id 
              FROM cms_content con 
            JOIN cms_category cat ON cat.id = con.category_id 
            WHERE con.lat = '' AND con.lng = '' AND cat.role = 'city' LIMIT 250";

        $result = $inDB->query($sql);

        $items = array();

        $count = $inDB->num_rows($result);

        if ($count) {

            while ($item = $inDB->fetch_assoc($result)){
                if($item['cat_parent_id']==215){
                    $item['country'] = "Россия";
                } else {
                    $item['country'] = $countries[$item['cat_parent_id']];
                }
                $items[] = $item;
            }
            
            ?>
        <p>Радиус города <input type="text" value="9" id="radius"> км.</p>
        <form action="" method="post">
            <table cellpadding="4" cellspacing="0" border="0" class="proptable" style="border:none">
                <tr>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Объект</strong>
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Страна</strong>
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Город</strong>
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Центр города</strong>
                    </td>
                    <td>
                        <strong>Долгота, широта маркера</strong>
                    </td>
                    <td>
                    </td>
                </tr>
                <?php foreach($items as $item){ ?>
                <tr class="item_row" rel="<?php echo $item['id']; ?>">
                    <td style="padding:0px;padding-right:20px;">
                        <strong style="color:#000"><?php echo $item['title']; ?></strong>
                    </td>
                    <td class="country" style="padding:0px;padding-right:20px;">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][country]" id="<?php echo $item['id']; ?>_country" value="<?php echo $item['country']; ?>" />
                    </td>
                    <td class="city" style="padding:0px;padding-right:20px;">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][city]" id="<?php echo $item['id']; ?>_city" value="<?php echo $item['city_title']; ?>" />
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lng]" id="<?php echo $item['id']; ?>_citylng" value="<?php echo $item['city_lng']; ?>" />
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lat]" id="<?php echo $item['id']; ?>_citylat" value="<?php echo $item['city_lat']; ?>" />
                    </td>
                    <td width="">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lng]" id="<?php echo $item['id']; ?>_lng" value="" />
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][lat]" id="<?php echo $item['id']; ?>_lat" value="" />
                    </td>
                    <td width="">
                        <a href="#" target="_blank" id="<?php echo $item['id']; ?>_link" style="display: none">на карте</a>
                    </td>
                </tr>
                <?php } ?>
            </table>

            <p class="start_detect">
                <span style="color: #09C">
                    <strong>Найдено объектов без координат:</strong>
                </span> <?php echo $count; ?> шт.
                <input style="margin-left:20px" type="button" value="Начать генерировать координаты" onclick="generateCoords();$(this).val('Подождите...').prop('disabled', 'disabled')" />
            </p>

            <p class="save_detect" style="display:none">
                <span style="color: #09C">
                    <strong>Генерация координат завершена</strong>
                </span>
                <input style="margin-left:20px" type="submit" name="save" value="Сохранить координаты" />
            </p>

        </form>
        
        <script>
function generateCoords(){

    if ($('tr.item_row').length==0) {
        $('.start_detect').hide();
        $('.save_detect').show();
        return;
    }

    var tr = $('tr.item_row').eq(0);
    
    var radius = parseInt($('#radius').val());

    var item_id = $(tr).attr('rel');
    
    var correct = 1000000;
    
    var city_lng = parseFloat($('#'+item_id+'_citylng').val());
    var city_lat = parseFloat($('#'+item_id+'_citylat').val());
    
    var lat_r = radius*0.0051*correct;
    var lng_r = radius*0.008*correct;
    
    var max_lat   = Math.round(city_lat*correct + lat_r);
    var min_lat   = Math.round(city_lat*correct - lat_r);
    
    var max_lng   = Math.round(city_lng*correct + lng_r);
    var min_lng   = Math.round(city_lng*correct - lng_r);
    
    var marker_lat = getRandom(min_lat, max_lat)/correct;
    var marker_lng = getRandom(min_lng, max_lng)/correct;
    
    $('#'+item_id+'_lat').val(marker_lat);
    $('#'+item_id+'_lng').val(marker_lng);
    $('#'+item_id+'_link').prop('href', 'http://maps.yandex.ru/?text='+marker_lat+','+marker_lng).show();

    $(tr).removeClass('item_row');

    if ($('tr.item_row').length==0) {
        $('.start_detect').hide();
        $('.save_detect').show();
        return;
    }
    setTimeout('generateCoords()', 100);
}

function getRandom (min, max){
    //var rand = min - 0.5 + Math.random()*(max-min+1);
    //return Math.round(rand);
    var rand = min + Math.random()*(max+1-min);
    return rand^0;
}
        </script>
        
                
        <?php } ?>

    <?php if (!$count) { ?>

        <p>В каталоге нет объектов без координат.</p>

    <?php } else { ?>
        

    <?php }
    } 
    
    //поиск координат панорам по координатам в определенном радиусе
    if($target == 'pangenerator'){
        
        if ($inCore->inRequest('save')){
            $items = $inCore->request('item', 'array');
            if (!is_array($items)) { $inCore->redirectBack(); }
            foreach($items as $item_id=>$pos){
                $item_id = intval($item_id);
                $inDB->query("UPDATE cms_content SET panlat='{$pos['panlat']}', panlng='{$pos['panlng']}' WHERE id = '{$item_id}'");
            }
            $inCore->redirect('index.php?view=tree');
        }

        cpAddPathway('Поиск координат панорам', $_SERVER['REQUEST_URI']);
        echo '<h3>Поиск координат панорам</h3>';
        
        $sql = "SELECT con.id as id, 
               con.panlat as addr_lat, 
               con.panlng as addr_lng, 
               con.title, 
               cat.lat as lat, cat.lng as lng 
            FROM cms_content con
            LEFT JOIN cms_category cat ON cat.id = con.category_id 
            WHERE con.panlat = '' AND con.panlng = '' LIMIT 200";

        $result = $inDB->query($sql);

        $items = array();

        $count = $inDB->num_rows($result);

        if ($count) {
            while ($item = $inDB->fetch_assoc($result)){
                $items[] = $item;
            }
        } ?>

    <?php if (!$count) { ?>

        <p>В каталоге нет панорам без координат. Поиск не требуется.</p>

    <?php } else { ?>
        <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&signed_in=true"></script>
        <script src='/components/content/systems/google/geo.js' type='text/javascript'></script>

        <?php echo $api_key; ?>
        
        <form action="" method="post">

            <table cellpadding="4" cellspacing="0" border="0" class="proptable" style="border:none">
                <tr>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Объект</strong>
                    </td>
                    <td style="padding:0px;padding-right:20px;">
                        <strong>Координаты населенного пункта</strong>
                    </td>
                    <td width="">
                        <strong>Долгота, широта панорамы</strong>
                    </td>
                </tr>
                <?php foreach($items as $item){ ?>
                <tr class="item_row" rel="<?php echo $item['id']; ?>">
                    <td style="padding:0px;padding-right:20px;">
                        <strong style="color:#000"><?php echo $item['title']; ?></strong>
                    </td>
                    <td class="latlng" style="padding:0px;padding-right:20px;">
                        <input type="text" style="width:100px" id="<?php echo $item['id']; ?>_lng" value="<?php echo $item['lng']; ?>" class="lng" disabled="disabled" />
                        <input type="text" style="width:100px" id="<?php echo $item['id']; ?>_lat" value="<?php echo $item['lat']; ?>" class="lat" disabled="disabled" />
                    </td>
                    <td width="">
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][panlng]" id="<?php echo $item['id']; ?>_panlng" value="" disabled="disabled" />
                        <input type="text" style="width:100px" name="item[<?php echo $item['id']; ?>][panlat]" id="<?php echo $item['id']; ?>_panlat" value="" disabled="disabled" />
                    </td>
                </tr>
                <?php } ?>
            </table>

            <p class="start_detect">
                <span style="color: #09C">
                    <strong>Найдено панорам без координат:</strong>
                </span> <?php echo $count; ?> шт.
                <input style="margin-left:20px" type="button" value="Начать поиск координат панорам..." onclick="detectLatLngListPanorama();$(this).val('Подождите...').prop('disabled', 'disabled')" />
            </p>

            <p class="save_detect" style="display:none">
                <span style="color: #09C">
                    <strong>Поиск завершен</strong>
                </span>
                <input style="margin-left:20px" type="submit" name="save" value="Сохранить координаты панорам" />
            </p>

        </form>
        
    <?php }

    }
    
    //копирование панорам, которые не захотели определяеться
    if($target == 'pancopy'){
        
        $sql = "SELECT con.id as id, 
               con.panlat as addr_lat, 
               con.panlng as addr_lng, 
               con.title, 
               cat.lat as lat, cat.lng as lng, cat.parent_id as cat_parent_id
            FROM cms_content con
            LEFT JOIN cms_category cat ON cat.id = con.category_id 
            WHERE con.panlat = 0";

        $result = $inDB->query($sql);

        $items = array();
        while ($item = $inDB->fetch_assoc($result)){
            
            //получаем успешную панораму из этой категории
            $sql_pan = "SELECT con.panlat as panlat, con.panlng as panlng 
            FROM cms_content con
            LEFT JOIN cms_category cat ON cat.id = con.category_id 
            WHERE con.panlat <> 0 AND cat.parent_id = {$item['cat_parent_id']} ORDER by RAND() LIMIT 1";
            $res_pan = $inDB->query($sql_pan);
            if($inDB->num_rows($res_pan)){
                $pan = $inDB->fetch_assoc($res_pan);
                $inDB->update('cms_content', $pan, $item['id']);
            }
            $items[] = $item;
        }
    }

} ?>