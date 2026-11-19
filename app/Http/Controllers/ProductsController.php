<?php

namespace App\Http\Controllers;

use App\Helper\FirebaseHelper;
use App\Http\Requests\ProductUpdateRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductsController extends Controller
{
    use FirebaseHelper;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('products.index', [
            'products' => Product::latest()->paginate(),
        ]);
    }

    public function create()
    {
        return view('products.create', [
            'categories' => ProductCategory::all(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $validator = Product::validate($request->all());
        if ($validator->fails()) {
            return redirect('products/create')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $file = $request->file('image');
        $path = $file->store('images', 'public');
        abort_unless($path, 500, 'Could not store the product image.');
        unset($data['image']);

        try {
            $product = Product::create([
                ...$data,
                'imageUrl' => 'storage/'.$path,
                'imageSize' => $file->getSize(),
                'imageType' => $file->getMimeType(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        $this->pushNotification(Customer::whereNotNull('device_id')->pluck('device_id')->all(), [
            'title' => 'New fish available',
            'message' => 'Browse the catalog to place your order.',
        ]);
        $request->session()->flash('product.name', $product->name);

        return redirect()->route('products.index');
    }

    public function show(Product $product)
    {
        return view('products.show', [
            'product' => $product,
        ]);
    }

    public function edit(Product $product)
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => ProductCategory::all(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product)
    {
        $product->update($request->validated());

        $request->session()->flash('product.name', $product->name);

        return redirect()->route('products.index');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');

        // Redirect back or return a response as needed
        // return redirect()->back()->with('success', 'Item deleted successfully.');
    }
}
