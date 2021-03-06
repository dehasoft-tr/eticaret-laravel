<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\UserCard;
use App\Http\Controllers\DataCrypter;

class CardController extends Controller
{

    /**
     * @OA\GET(
     * path="/api/user/card/get",
     * summary="Kartları getir",
     * description="Kullanıcının kayıtlı kartlarını listeler.",
     * operationId="userCardGet",
     * tags={"Kullanıcı Kart"},
     * security={{"deha_token":{}}},
     * @OA\RequestBody(
     *    required=true,
     *    description="Kartları listeler.",
     *    @OA\JsonContent(
     *       required={},
     *    ),
     * ),
     * @OA\Response(
     *    response=200,
     *    description="Kullanıcı kartları başarı ile sorgulandı",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Kullanıcı kartları başarı ile sorgulandı"),
     *        )
     *     )
     * )
     */
    public function get(Request $request){
        try {
            $user = $request->get('user');
            $cards = $user->getCard->toArray();
            return response()->json([
                'error' => false,
                'message' => 'Kullanıcı kartları başarı ile sorgulandı.',
                'cards' => array_map(function ($card) {
                    return [
                        'id' => $card['id'],
                        'card_number' => $card['card_number'],
                        'card_expire' => $card['card_expire'],
                        'card_cvv' => $card['card_cvv'],
                        'card_name' => $card['card_name'],
                    ];
                }, $cards)
            ]);
        } catch (\Exception $ex) {
            return response()->json([
                'error' => true,
                'message' => 'Kartları sorgularken bir hata oluştu.',
                'exception' =>$ex
            ]);
        }
        return response()->json([
            'error' => true,
            'message' => 'Kartları sorgularken bir hata oluştu.'
        ]);
    }

    /**
     * @OA\POST(
     * path="/api/user/card/create",
     * summary="Kart Oluştur",
     * description="Kullanıcı kart kaydeder.",
     * operationId="userCardCreate",
     * tags={"Kullanıcı Kart"},
     * security={{"deha_token":{}}},
     * @OA\RequestBody(
     *    required=true,
     *    description="Kartları oluşturur.",
     *    @OA\JsonContent(
     *       required={"card_number","card_expire","card_cvv","card_name"},
     *          @OA\Property(property="card_number", type="string", example="1234567890123456"),
     *          @OA\Property(property="card_expire", type="string", example="12/20"),
     *          @OA\Property(property="card_cvv", type="string", example="123"),
     *          @OA\Property(property="card_name", type="string", example="Banka Kartım"),
     *    ),
     * ),
     * @OA\Response(
     *    response=200,
     *    description="Kullanıcı kartı başarı ile oluşturuldu",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Kullanıcı kartı başarı ile oluşturuldu"),
     *        )
     *     )
     * )
     */
    public function create(Request $request){
        $validation = Validator::make($request->all(), [
            'card_number' => 'required|min:16|max:16',
            'card_name' => 'required|min:2|max:45',
            'card_expire' => 'required|min:3|max:15',
            'card_cvv' => 'required|min:3|max:4'
        ]);
        if ($validation->fails() || strstr($request->card_expire, '/')==false) {
            $messages = [
                'card_number' => ($validation->getMessageBag())->messages()['card_number'] ?? 'success',
                'card_name' => ($validation->getMessageBag())->messages()['card_name'] ?? 'success',
                'card_expire' => ($validation->getMessageBag())->messages()['card_expire'] ?? 'success',
                'card_cvv' => ($validation->getMessageBag())->messages()['card_cvv'] ?? 'success',
                'expire'=> strstr($request->card_expire, '/')!=false ? 'success' : 'Kartın son kullanma tarihi formatı hatalı'
            ];
            return response()->json([
                'error' => true,
                'message' => 'Bu işlem için gerekli bilgiler eksik.',
                'validation' => array_filter($messages, function ($value) {
                    return $value != 'success';
                })
            ], 400);
        }
        try {
            $id = $request->get('user_id');
            $cards = User::where('id', $id)->first()->getCard;
            $crypt = new DataCrypter;

            $card_number = $crypt->crypt_router($request->card_number, 'encode');
            $card_expire = $crypt->crypt_router($request->card_expire, 'encode');
            $card_cvv = $crypt->crypt_router($request->card_cvv, 'encode');
            if (count($cards) >= 4) {
                return response()->json([
                    'error' => true,
                    'message' => 'Kart ekleme limitiniz dolmuştur.',
                ], 400);
            }
            $card = UserCard::insertGetId([
                'card_number' => $card_number,
                'card_name' => $request->card_name,
                'card_expire' => $card_expire,
                'card_cvv' => $card_cvv,
                'user_id' => $id
            ]);
            if ($card) {
                return response()->json([
                    'error' => false,
                    'message' => 'Kartınız başarıyla eklendi.',
                    'card' => [
                        'id' => $card,
                        'card_number' => $request->card_number,
                        'card_name' => $request->card_name,
                        'card_expire' => $request->card_expire,
                        'card_cvv' => $request->card_cvv
                    ]
                ], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'error' => true,
                'message' => 'Beklenmedik bir hata oluştu.',
                'exception' => $ex
            ], 403);
        }
        return response()->json([
            'error' => true,
            'message' => 'Beklenmedik bir hata oluştu.',
        ], 403);
    }

    /**
     * @OA\POST(
     * path="/api/user/card/delete",
     * summary="Kart Sil",
     * description="Kullanıcının kayıtlı kartı siler.",
     * operationId="userCardDelete",
     * tags={"Kullanıcı Kart"},
     * security={{"deha_token":{}}},
     * @OA\RequestBody(
     *    required=true,
     *    description="Kartı siler.",
     *    @OA\JsonContent(
     *       required={"card_id"},
     *          @OA\Property(property="card_id", type="integer", example="1"),
     *    ),
     * ),
     * @OA\Response(
     *    response=200,
     *    description="Kullanıcı kartı başarı ile güncellendi",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Kullanıcı kartı başarı ile güncellendi"),
     *        )
     *     )
     * )
     */
    public function delete(Request $request){
        $validation = Validator::make($request->all(), [
            'card_id' => 'required|numeric'
        ]);
        if ($validation->fails()) {
            $messages = [
                'card_id' => ($validation->getMessageBag())->messages()['card_id'] ?? 'success'
            ];
            return response()->json([
                'error' => true,
                'message' => 'Bu işlem için gerekli bilgiler eksik.',
                'validation' => array_filter($messages, function ($value) {
                    return $value != 'success';
                })
            ], 400);
        }
        try {
            $id = $request->get('user_id');
            $card = UserCard::where('id', $request->card_id)->where('user_id', $id)->first();
            if ($card) {
                $card->delete();
                return response()->json([
                    'error' => false,
                    'message' => 'Kartınız başarıyla silindi.'
                ], 200);
            }
        } catch (\Exception $ex) {
            return response()->json([
                'error' => true,
                'message' => 'Beklenmedik bir hata oluştu.',
                'exception' => $ex
            ], 403);
        }
        return response()->json([
            'error' => true,
            'message' => 'Beklenmedik bir hata oluştu.',
        ], 403);
    }

    /**
     * @OA\POST(
     * path="/api/user/card/update",
     * summary="Kart Güncelle",
     * description="Kullanıcının kayıtlı kartı günceller.",
     * operationId="userCardUpdate",
     * tags={"Kullanıcı Kart"},
     * security={{"deha_token":{}}},
     * @OA\RequestBody(
     *    required=true,
     *    description="Kartları günceller.",
     *    @OA\JsonContent(
     *       required={},
     *    ),
     * ),
     * @OA\Response(
     *    response=200,
     *    description="Kullanıcı kartı başarı ile güncellendi",
     *    @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Kullanıcı kartı başarı ile güncellendi"),
     *        )
     *     )
     * )
     */
    public function update(Request $request){
        $validation=Validator::make($request->all(),[
            'card_id'=>'required|numeric',
            'card_number'=>'required|min:16|max:16',
            'card_name'=>'required|min:2|max:45',
            'card_expire'=>'required|min:3|max:15',
            'card_cvv'=>'required|min:3|max:4'
        ]);
        if($validation->fails() || strstr($request->card_expire, '/')==false){
            $messages=[
                'card_id'=>($validation->getMessageBag())->messages()['card_id']??'success',
                'card_number'=>($validation->getMessageBag())->messages()['card_number']??'success',
                'card_name'=>($validation->getMessageBag())->messages()['card_name']??'success',
                'card_expire'=>($validation->getMessageBag())->messages()['card_expire']??'success',
                'card_cvv'=>($validation->getMessageBag())->messages()['card_cvv']??'success',
                'expire' => strstr($request->card_expire, '/') != false ? 'success' : 'Kartın son kullanma tarihi formatı hatalı'
            ];
            return response()->json([
                'error'=>true,
                'message'=>'Bu işlem için gerekli bilgiler eksik.',
                'validation'=>array_filter($messages,function($value){
                    return $value!='success';
                })
            ],400);
        }
        try{
            $id=$request->get('user_id');
            $card=UserCard::where('id',$request->card_id)->where('user_id',$id)->first();
            if($card){
                $crypt=new DataCrypter;
                $card_number=$crypt->crypt_router($request->card_number,false,'encode');
                $card_expire=$crypt->crypt_router($request->card_expire,false,'encode');
                $card_cvv=$crypt->crypt_router($request->card_cvv,false,'encode');
                $card->update([
                    'card_number'=>$card_number,
                    'card_name'=>$request->card_name,
                    'card_expire'=>$card_expire,
                    'card_cvv'=>$card_cvv
                ]);
                return response()->json([
                    'error'=>false,
                    'message'=>'Kartınız başarıyla güncellendi.',
                    'card'=>[
                        'id'=>$card->id,
                        'card_number'=>$request->card_number,
                        'card_name'=>$request->card_name,
                        'card_expire'=>$request->card_expire,
                        'card_cvv'=>$request->card_cvv
                    ]
                ],200);
            }
        }catch(\Exception $ex){
            return response()->json([
                'error'=>true,
                'message'=>'Beklenmedik bir hata oluştu.',
                'exception'=>$ex
            ],403);
        }
        return response()->json([
            'error'=>true,
            'message'=>'Beklenmedik bir hata oluştu.',
        ],403);
    }
}
