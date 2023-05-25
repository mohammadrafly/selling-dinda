<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Items;
use App\Models\Transactions;
use App\Models\Users;

class TransactionController extends BaseController
{
    public function index()
    {
        $model = new Transactions();
        $modelItems = new Items();
        $modelUsers = new Users();

        if ($this->request->getMethod(true) !== 'POST') {
            $data = [
                'content' => $model->findAll(),
                'barang' => $items = $modelItems->where('selling_price IS NOT NULL')->findAll(),
                'user' => $modelUsers->findUserByRole('customer'),
            ];
            return view('pages/dashboard/transaction', $data);
        }

       
        $transactions = []; // Array to store all the transaction data

        $id_items = $this->request->getPost('id_items[]'); // Get all id_item values
        $quantities = $this->request->getPost('quantity[]'); // Get all quantity values
        $total_prices = $this->request->getPost('total_price[]'); // Get all total_price values
        $cicil = $this->request->getPost('cicil');
        $user_id = $this->request->getPost('user_id');
        $payment_method = $this->request->getPost('payment_type');
        if ($payment_method == 'hutang') {
            $status = 'cicil';
            $bayar = '0';
        } else {
            $status = 'done';
            $bayar = $cicil;
        }
        $transactionCode = 'TRX-' . bin2hex(random_bytes(16));
        $currentDate = date('Y-m-d');
        $nextDay = date('Y-m-d', strtotime($currentDate . ' +1 day'));

        // Loop through all the values and create an array for each transaction
        for ($i = 0; $i < count($id_items); $i++) {
            $transaction = [
                'id_item' => $id_items[$i],
                'quantity' => $quantities[$i],
                'payment_type' => $payment_method,
                'transaction_code' => $transactionCode,
                'total_price' => $total_prices[$i],
                'status' => $status,
                'user_id' => $user_id,
                'cicil' => $bayar,
                'created_at' => $nextDay,
                'updated_at' => $nextDay

            ];
            $transactions[] = $transaction; // Add the transaction to the array
        }
        
        if ($model->insertBatch($transactions)) {
            $updatedItems = [];

            for ($i = 0; $i < count($id_items); $i++) {
                $id_item = $id_items[$i];
                $quantity = $quantities[$i];
                
                // Retrieve the item by its ID
                $item = $modelItems->find($id_item);
                
                if ($item) {
                    // Update the item's stock by decrementing the quantity
                    $item['stock'] -= $quantity;

                    // Set the updated_at field
                    $item['updated_at'] = $nextDay;
                    
                    // Add the updated item to the array
                    $updatedItems[] = (array) $item;
                }
            }
            if (!empty($updatedItems)) {
                // Perform batch update
                $modelItems->updateBatch($updatedItems, 'id');
                
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Berhasil melakukan transaksi'
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Tidak ada item yang ditemukan'
                ]);
            }
        } else {
            return $this->response->setJSON([
                'success' => FALSE,
                'message' => 'Gagal melakukan transaksi'
            ]);
        }
    }

    public function update($id = null)
    {
        $model = new Transactions();

        if ($this->request->getMethod(true) !== 'POST') {
            $data = [
                'content' => $model->where('id', $id)->first()
            ];

            return view('pages/dashboard/transactionUpdate', $data);
        }

        $data = [
            'id_item' => $this->request->getVar('id_item'),
            'quantity' => $this->request->getVar('quantity'),
            'payment_type' => $this->request->getVar('payment_type'),
            'status' => $this->request->getVar('status'),
            'updated_at' => date('Y-m-d'),
        ];
        
        if ($model->update($id, $data)) {
            return redirect()->to('dashboard/transaction')->with('success', 'Berhasil update data transaksi');
        } else {
            return redirect()->to('dashboard/transaction')->with('error', 'Gagal update data transaksi');
        }
    }

    public function delete($id = null) 
    {
        $model = new Transactions();
        if ($model->where('id', $id)->delete($id)) {
            return redirect()->to('dashboard/transaction')->with('success', 'Berhasil hapus data barang');
        } else {
            return redirect()->to('dashboard/transaction')->with('error', 'Gagal hapus data barang');
        }
    }
}
