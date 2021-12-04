<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\View;
use App\Http\Controllers\DataCrypter;

class ProductController extends Controller
{

    /**
     * Ürünleri listele
     */
    public function get(Request $request){
        try{
            if (isset($request->allSearch)) {
                $request->name = $request->allSearch;
                $request->price = $request->allSearch;
                $request->stock = $request->allSearch;
                $request->description = $request->allSearch;
                $request->slug = $request->allSearch;
            }
            $products = Product::with('getDiscounts')
            ->where('active', 1)
                ->where('stock', '>', 0)
                ->where('id', '>=', $request->start ?? 1)
                ->where('id', '<=', $request->end ?? 2)
                ->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%' . ($request->name ?? '') . '%')
                        ->orWhere('description', 'like', '%' . ($request->description ?? '') . '%')
                        ->orWhere('slug', 'like', '%' . ($request->slug ?? '') . '%')
                        ->orWhere('price', 'like', '%' . ($request->price ?? '') . '%')
                        ->orWhere('stock', 'like', '%' . ($request->stock ?? '') . '%');
                })
                ->get();
            $response = [];
            foreach ($products as $key => $value) {
                $type = $value->getFirstImage['type'] ?? 'url';
                $path = $value->getFirstImage['path'] ?? '';
                $discountPrice = 0;
                $discounts = [];
                $list = $value->getDiscounts;
                for ($i = 0; $i < count($list); $i++) {
                    $current = $list[$i]->getDiscount;
                    $newdiscount = $value->price * $current->percent / 100;
                    $discountPrice += $newdiscount;
                    array_push($discounts, [
                        'id' => "product-" . $current->id,
                        'discount' => $current->percent,
                        'name' => $current->name,
                        'description' => $current->description,
                    ]);
                }
                $list = $value->getCategory->getDiscounts;
                for ($i = 0; $i < count($list); $i++) {
                    $current = $list[$i]->getDiscount;
                    $newdiscount = $value->price * $current->percent / 100;
                    $discountPrice += $newdiscount;
                    array_push($discounts, [
                        'id' => "category-" . $current->id,
                        'discount' => $current->percent,
                        'name' => $current->name,
                        'description' => $current->description,
                    ]);
                }
                array_push($response, [
                    'product_id' => $value->id,
                    'name' => $value->name,
                    'price' => $value->price,
                    'stock' => $value->stock,
                    'description' => $value->description,
                    'discounts' => $discounts,
                    'discountPrice' => $discountPrice,
                    'imageType' => $type,
                    'image' => $path
                ]);
            }
            return response()->json([
                'error' => false,
                'message' => 'Ürünler başarı ile sorgulandı.',
                'products' => $response
            ], 200);
        }catch(\Exception $e){
            return response()->json([
                'error' => true,
                'message' => 'Ürünler sorgulanırken bir hata oluştu.',
                'errorMessage' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Ürünü getir
     */
    public function detail($product_id,Request $request){
        try {
            $product = Product::with('getDiscounts')
                ->where('active', 1)
                ->where(function($query) use($product_id){
                    $query->where('id', $product_id)
                        ->orWhere('slug', $product_id);
                })
                ->first();
            if (!$product) {
                return response()->json([
                    'error' => true,
                    'message' => 'Ürün bulunamadı.'
                ], 404);
            }
            $type = $product->getFirstImage['type'] ?? 'url';
            $path = $product->getFirstImage['path'] ?? '';
            $discountPrice = 0;
            $discounts = [];
            $list = $product->getDiscounts;
            for ($i = 0; $i < count($list); $i++) {
                $current = $list[$i]->getDiscount;
                $newdiscount = $product->price * $current->percent / 100;
                $discountPrice += $newdiscount;
                array_push($discounts, [
                    'id' => "product-" . $current->id,
                    'discount' => $current->percent,
                    'name' => $current->name,
                    'description' => $current->description,
                ]);
            }
            $list = $product->getCategory->getDiscounts;
            for ($i = 0; $i < count($list); $i++) {
                $current = $list[$i]->getDiscount;
                $newdiscount = $product->price * $current->percent / 100;
                $discountPrice += $newdiscount;
                array_push($discounts, [
                    'id' => "category-" . $current->id,
                    'discount' => $current->percent,
                    'name' => $current->name,
                    'description' => $current->description,
                ]);
            }
            $checkView=View::where('product_id',$product->id)
            ->where('url', $request->fullUrl())
            ->where(function($query) use($request){ 
                $query
                ->where('user_id',($request->get('user')->id??-1))
                ->orWhere('ip', $request->ip());
            })
            ->first();
            if($checkView==null){
                $newData=[];
                $newData['product_id']=$product->id;
                $newData['user_id']=($request->get('user')->id??-1);
                $newData['ip']=$request->ip();
                $newData['url']=$request->fullUrl();
                if($newData['user_id']==-1){
                    unset($newData['user_id']);
                }
                View::insert([$newData]);
            }else{
                $checkView->count = $checkView->count + 1;
                $checkView->save();
            }
            
            return response()->json([
                'error' => false,
                'message' => 'Ürün başarı ile sorgulandı.',
                'product' => [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'slug' => $product->slug,
                    'description' => $product->description,
                    'discounts' => $discounts,
                    'discountPrice' => $discountPrice,
                    'images'=> array_map(function ($item) {
                        return [
                            'id'=> $item['id'],
                            'type'=> ($item['type'] ?? 'url'),
                            'path'=> ($item['path'] ?? '')
                        ];
                    }, $product->getImage->toArray()),
                    'category' => $product->getCategory->name,
                    'created_at' => DataCrypter::timeHasPassed(date('Y-m-d H:i:s', strtotime($product->created_at))),
                    'updated_at' => DataCrypter::timeHasPassed(date('Y-m-d H:i:s', strtotime($product->updated_at)))
                ]
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'error' => true,
                'message' => 'Ürün sorgulanırken bir hata oluştu.',
                'exception'=>$th->getMessage()
            ], 400);
        }
        return response()->json([
            'error' => true,
            'message' => 'Ürün sorgulanırken bir hata oluştu.'
        ], 400);
    }
    /**
     * Ürün keşfet
    */
    public function discover(Request $request){
        if (isset($request->allSearch)) {
            $request->name = $request->allSearch;
            $request->price = $request->allSearch;
            $request->stock = $request->allSearch;
            $request->description = $request->allSearch;
            $request->slug = $request->allSearch;
        }
        $products = Product::with('getDiscounts')
                            ->where('active', 1)
                            ->where('stock', '>', 0)
                            ->where(function ($query) use ($request) {
                                $query->where('name', 'like', '%' . ($request->name ?? '') . '%')
                                    ->orWhere('description', 'like', '%' . ($request->description ?? '') . '%')
                                    ->orWhere('slug', 'like', '%' . ($request->slug ?? '') . '%')
                                    ->orWhere('price', 'like', '%' . ($request->price ?? '') . '%')
                                    ->orWhere('stock', 'like', '%' . ($request->stock ?? '') . '%');
                            })
                            ->inRandomOrder()
                            ->limit($request->count??20)
                            ->get();
        $response=[];
        foreach ($products as $key => $value) {
            $type = $value->getFirstImage['type']??'url';
            $path = $value->getFirstImage['path']??'';
            $discountPrice = 0;
            $discounts=[];
            $list=$value->getDiscounts;
            for ($i=0; $i < count($list); $i++) { 
                $current= $list[$i]->getDiscount;
                $newdiscount=$value->price*$current->percent/100;
                $discountPrice+= $newdiscount;
                array_push($discounts,[
                    'id'=>"product-".$current->id,
                    'discount'=>$current->percent,
                    'name'=>$current->name,
                    'description'=>$current->description,
                ]);
            }
            $list=$value->getCategory->getDiscounts;
            for ($i=0; $i < count($list); $i++) {
                $current = $list[$i]->getDiscount;
                $newdiscount = $value->price * $current->percent / 100;
                $discountPrice += $newdiscount;
                array_push($discounts, [
                    'id' => "category-".$current->id,
                    'discount' => $current->percent,
                    'name' => $current->name,
                    'description' => $current->description,
                ]);
            }
            array_push($response,[
                'product_id'=>$value->id,
                'name'=>$value->name,
                'price'=>$value->price,
                'description'=>$value->description,
                'discounts'=>$discounts,
                'discountPrice'=>$discountPrice,
                'imageType' => $type,
                'image' => $path
            ]);
        }
        return response()->json([
            'error'=>false,
            'message'=>'Ürünler başarı ile sorgulandı.',
            'products' => $response
        ],200);
    }

    /**
     * Ürün oluştur
     */
    public function create(Request $request){
        $validation = Validator::make($request->all(), [
            'name' => 'required',
            'description' => 'required',
            'price' => 'required',
            'category_id' => 'required'
        ]);
        if ($validation->fails()) {
            $messages = [
                'name' => ($validation->getMessageBag())->messages()['name'] ?? 'success',
                'description' => ($validation->getMessageBag())->messages()['description'] ?? 'success',
                'price' => ($validation->getMessageBag())->messages()['price'] ?? 'success',
                'category_id' => ($validation->getMessageBag())->messages()['category_id'] ?? 'success'
            ];
            return response()->json([
                'error' => true,
                'message' => 'Bu işlem için gerekli bilgiler eksik.',
                'validation' => array_filter($messages, function ($e) {
                    if ($e != 'success') {
                        return true;
                    }
                })
            ], 401);
        }
        try {
            $price = doubleval($request->price);
            $slugify = DataCrypter::slugify($request->name);
            $checkSlugs = Product::where('slug', 'like', "%" . $slugify . "%")->get();
            $slug = count($checkSlugs) > 0 ? $slugify . '-' . count($checkSlugs) : $slugify;
            $product = new Product;
            $product->name = $request->name;
            $product->description = $request->description;
            $product->price = $price;
            $product->stock = floor(intval($request->stock)) ?? 0;
            $product->category_id = $request->category_id;
            $product->slug = $slug;
            $product->save();
            return response()->json([
                'error' => false,
                'message' => 'Ürün başarıyla eklendi.',
                'product' => $product
            ], 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => true, 'message' => 'Ürün eklenirken bir hata oluştu.', 'exception' => $ex], 401);
        }
    }

    /**
     * Ürünü güncelle
     */
    public function update(Request $request){
        $validation = Validator::make($request->all(), [
            'product_id' => 'required|numeric',
            'name' => 'required|min:3|max:255',
            'description' => 'required|min:3',
            'price' => 'required',
            'category' => 'required',
        ]);
        if ($validation->fails()) {
            $messages = [
                'name' => ($validation->getMessageBag())->messages()['name'] ?? 'success',
                'description' => ($validation->getMessageBag())->messages()['description'] ?? 'success',
                'price' => ($validation->getMessageBag())->messages()['price'] ?? 'success',
                'category' => ($validation->getMessageBag())->messages()['category_id'] ?? 'success',
                'product_id' => ($validation->getMessageBag())->messages()['product_id'] ?? 'success',
            ];
            return response()->json([
                'error' => true,
                'message' => 'Bu işlem için gerekli bilgiler eksik.',
                'validation' => array_filter($messages, function ($e) {
                    if ($e != 'success') {
                        return true;
                    }
                })
            ], 401);
        }
        try {
            $price = doubleval($request->price);
            $slugify = DataCrypter::slugify($request->name);
            $checkSlugs = Product::where('slug', 'like', "%" . $slugify . "%")->get();
            $product = Product::find($request->id);
            $slug = $product->name!=$request->name ? count($checkSlugs) > 0 ? $slugify . '-' . count($checkSlugs) : $slugify : $product->slug;
            $product->name = $request->name;
            $product->description = $request->description;
            $product->price = $price;
            $product->stock = floor(intval($request->stock??0));
            $product->category_id = $request->category_id;
            $product->slug = $slug;
            $product->save();
            return response()->json([
                'error' => false,
                'message' => 'Ürün başarıyla güncellendi.',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stock' => $product->stock,
                    'category' => $product->category->name,
                    'image' => $product->getImage,
                    'discount' => $product->discount->name ?? null,
                    'slug' => $product->slug
                ]
            ], 200);
        } catch (\Exception $ex) {
            return response()->json(['error' => true, 'message' => 'Ürün güncellenirken bir hata oluştu.', 'exception' => $ex], 401);
        }
        return response()->json(['error' => true, 'message' => 'Ürün bulunamadı.'], 401);
    }

    /**
    * Ürün sil
    */
    public function delete(Request $request){
        $validation = Validator::make($request->all(), [
            'product_id' => 'required|numeric'
        ]);
        if ($validation->fails()) {
            $messages = [
                'product_id' => ($validation->getMessageBag())->messages()['product_id'] ?? 'success',
            ];
            return response()->json([
                'error' => true,
                'message' => 'Bu işlem için gerekli bilgiler eksik.',
                'validation' => array_filter($messages, function ($e) {
                    if ($e != 'success') {
                        return true;
                    }
                })
            ], 401);
        }
        try {
            $product = Product::find($request->product_id);
            if($product){
                $product->delete();
                return response()->json([
                    'error' => false,
                    'message' => 'Ürün başarıyla silindi.'
                ], 200);
            }
            return response()->json(['error' => true, 'message' => 'Ürün bulunamadı.'], 400);
        } catch (\Exception $ex) {
            return response()->json(['error' => true, 'message' => 'Ürün silinirken bir hata oluştu.', 'exception' => $ex], 403);
        }
        return response()->json(['error' => true, 'message' => 'Ürün bulunamadı.'], 400);
    }
}
